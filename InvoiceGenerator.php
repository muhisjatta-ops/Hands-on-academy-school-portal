<?php

namespace App\Services;

use App\Models\Discount;
use App\Models\FeeStructure;
use App\Models\Invoice;
use App\Models\Student;
use App\Models\Term;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Turns the price list into actual charges.
 *
 * Two rules hold this together.
 *
 * FIRST: an invoice line COPIES the description and amount from the fee
 * structure at the moment of billing. It does not point at the price list
 * and read it later. Raising school fees in March must not rewrite what a
 * parent was told in January.
 *
 * SECOND: fee items marked NEW_ONLY — registration, uniform, and in upper
 * basic the book bill — are charged only to new and re-registering students.
 * This is how the published bill's paired Term 1 figures work, and it is a
 * different thing from a discount. A returning Grade 1-3 pupil is not
 * getting 2,100 off a 10,500 bill; they were never charged those items.
 * Modelling it as a discount would overstate both the billed total and the
 * collection rate on every report the bursar runs.
 */
class InvoiceGenerator
{
    public function forStudent(Student $student, Term $term): Invoice
    {
        return DB::transaction(function () use ($student, $term) {
            $existing = Invoice::where('student_id', $student->id)
                ->where('term_id', $term->id)
                ->first();

            if ($existing) {
                return $existing;
            }

            $enrollment = $student->currentEnrollment();

            if (! $enrollment) {
                throw new \RuntimeException(
                    $student->full_name . ' is not enrolled in a class for the current year.'
                );
            }

            $gradeLevel = $enrollment->classRoom->gradeLevel;
            $billAsNew = $this->billAsNew($student, $gradeLevel);

            $structures = FeeStructure::with('category')
                ->where('grade_level_id', $gradeLevel->id)
                ->where('term_id', $term->id)
                // The whole new/returning rule, in one clause.
                ->when(! $billAsNew, fn ($q) => $q->where('applies_to', 'ALL'))
                ->get();

            if ($structures->isEmpty()) {
                throw new \RuntimeException(
                    'No fees have been set for ' . $gradeLevel->name
                    . ' in ' . $term->label() . '.'
                );
            }

            $invoice = Invoice::create([
                'number'     => Invoice::nextNumber(),
                'student_id' => $student->id,
                'term_id'    => $term->id,
                'issue_date' => $term->start_date,
                'due_date'   => $term->start_date->copy()->addDays(30),
                'status'     => 'ISSUED',
                'created_by' => Auth::id(),
            ]);

            $discounts = Discount::where('student_id', $student->id)
                ->activeOn($term->start_date)
                ->get();

            foreach ($structures as $structure) {
                $charge = (float) $structure->amount;

                $invoice->lines()->create([
                    'description'      => $structure->category->name,
                    'amount'           => $charge,
                    'fee_structure_id' => $structure->id,
                ]);

                // Discounts are their own negative lines, so a receipt shows
                // the full fee and the relief separately. Parents and
                // auditors both want to see the two numbers.
                foreach ($discounts as $discount) {
                    $applies = is_null($discount->fee_category_id)
                        || $discount->fee_category_id === $structure->fee_category_id;

                    if (! $applies) {
                        continue;
                    }

                    $off = $discount->amountOff($charge);

                    if ($off > 0) {
                        $invoice->lines()->create([
                            'description' => $discount->reason . ' — ' . $structure->category->name,
                            'amount'      => -$off,
                        ]);
                    }
                }
            }

            return $invoice->load('lines');
        });
    }

    /**
     * Is this student billed at the new rate for this term?
     *
     * Two ways to qualify, and the second is easy to miss: the bill states
     * that Level 1, Grade 1 and Grade 7 are all treated as new students,
     * because they are the entry year of each stage. A child moving up from
     * ECD Level 3 into Grade 1 has been at the school for years and is still
     * billed as new.
     */
    private function billAsNew(Student $student, $gradeLevel): bool
    {
        if ($gradeLevel->is_stage_entry) {
            return true;
        }

        return (bool) $student->is_new_registration;
    }

    /**
     * What a student would be billed, without creating anything. Use this to
     * quote a parent before the invoice run, and to show both rates side by
     * side on the fee structure screen.
     */
    public function quote(Student $student, Term $term): array
    {
        $enrollment = $student->currentEnrollment();

        if (! $enrollment) {
            throw new \RuntimeException($student->full_name . ' is not enrolled.');
        }

        $gradeLevel = $enrollment->classRoom->gradeLevel;

        $all = FeeStructure::with('category')
            ->where('grade_level_id', $gradeLevel->id)
            ->where('term_id', $term->id)
            ->get();

        $billAsNew = $this->billAsNew($student, $gradeLevel);

        $lines = $all
            ->filter(fn ($s) => $billAsNew || $s->applies_to === 'ALL')
            ->map(fn ($s) => [
                'description' => $s->category->name,
                'amount'      => (float) $s->amount,
                'new_only'    => $s->applies_to === 'NEW_ONLY',
            ])
            ->values();

        return [
            'student'      => $student->full_name,
            'grade_level'  => $gradeLevel->name,
            'term'         => $term->label(),
            'billed_as'    => $billAsNew ? 'new' : 'returning',
            'stage_entry'  => (bool) $gradeLevel->is_stage_entry,
            'lines'        => $lines,
            'total'        => round($lines->sum('amount'), 2),
            // Both rates, so the office can answer "what would it be if..."
            'rate_new'     => round((float) $all->sum('amount'), 2),
            'rate_returning' => round(
                (float) $all->where('applies_to', 'ALL')->sum('amount'), 2
            ),
        ];
    }

    /**
     * Bill a class or the whole school. Returns a per-student report rather
     * than throwing on the first problem — one unenrolled child should not
     * abort billing for everyone else.
     */
    public function forClassRoom(?int $classRoomId, Term $term): array
    {
        $query = Student::active();

        if ($classRoomId) {
            $query->inClassRoom($classRoomId);
        }

        $report = ['created' => 0, 'existing' => 0, 'new_rate' => 0, 'returning_rate' => 0, 'failed' => []];

        foreach ($query->cursor() as $student) {
            try {
                $before = Invoice::where('student_id', $student->id)
                    ->where('term_id', $term->id)
                    ->exists();

                $invoice = $this->forStudent($student, $term);

                if ($before) {
                    $report['existing']++;
                } else {
                    $report['created']++;

                    $enrollment = $student->currentEnrollment();
                    $this->billAsNew($student, $enrollment->classRoom->gradeLevel)
                        ? $report['new_rate']++
                        : $report['returning_rate']++;
                }
            } catch (\Throwable $e) {
                $report['failed'][] = [
                    'student' => $student->full_name,
                    'reason'  => $e->getMessage(),
                ];
            }
        }

        return $report;
    }
}

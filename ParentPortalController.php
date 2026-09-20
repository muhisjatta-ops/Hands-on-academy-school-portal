<?php

namespace App\Http\Controllers;

use App\Models\AttendanceRecord;
use App\Models\Student;
use App\Models\Term;
use App\Services\GradeCalculator;
use App\Services\PaymentRecorder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * The parent portal.
 *
 * ONE RULE GOVERNS THIS ENTIRE FILE: every query starts from the signed-in
 * guardian and works outwards. Not one method takes a student id and then
 * checks whether the parent is allowed to see it.
 *
 * The difference matters more than it looks:
 *
 *     // WRONG — the record is fetched first, so a forgotten check leaks it
 *     $student = Student::findOrFail($id);
 *     if (! $parentOwns($student)) abort(403);
 *
 *     // RIGHT — the record cannot be fetched at all unless it is theirs
 *     $student = $this->childOrFail($id);
 *
 * With the first shape, one method written in a hurry exposes every child in
 * the school to every guardian account. With the second, a forgotten check
 * returns nothing, because the query never reached outside the family.
 *
 * Note also that childOrFail throws 404, not 403. A 403 would confirm that a
 * student with that id exists — a parent could walk the ids and learn the
 * size of the roll and which ids are real. 404 tells them nothing.
 */
class ParentPortalController
{
    /**
     * The guardian record behind the signed-in user.
     *
     * A user with the parent role but no guardian row is a setup mistake, not
     * an attack — but it must still return nothing rather than everything.
     */
    private function guardian()
    {
        $guardian = Auth::user()?->guardian;

        if (! $guardian) {
            throw new NotFoundHttpException('No guardian record is linked to this account.');
        }

        return $guardian;
    }

    /**
     * Resolve a child id against THIS guardian's children only.
     *
     * This is the single gate. Every method that needs a student goes
     * through it, and nothing else in this controller may call
     * Student::find in any form.
     */
    private function childOrFail($studentId): Student
    {
        $student = $this->guardian()
            ->students()
            ->where('students.id', $studentId)
            ->first();

        if (! $student) {
            // Deliberately indistinguishable from "no such student".
            throw new NotFoundHttpException('Student not found.');
        }

        return $student;
    }

    /* ---------------------------------------------------------------- */

    /** The children this guardian may see, and nothing else. */
    public function children()
    {
        $term = Term::current();

        $children = $this->guardian()
            ->students()
            ->with(['enrollments.classRoom.gradeLevel'])
            ->orderBy('first_name')
            ->get();

        return response()->json([
            'guardian' => [
                'name'  => $this->guardian()->full_name,
                'phone' => $this->guardian()->phone,
            ],
            'term'     => $term?->label(),
            'children' => $children->map(function (Student $s) use ($term) {
                $enrollment = $s->currentEnrollment();

                return [
                    'id'         => $s->id,
                    'name'       => $s->full_name,
                    'student_id' => $s->admission_number,
                    'class'      => $enrollment?->classRoom->name,
                    'status'     => $s->status,
                    'balance'    => $s->outstandingBalance(),
                    'attendance' => $term
                        ? AttendanceRecord::rateFor($s->id, $term->id)
                        : null,
                ];
            }),
        ]);
    }

    /** One child's summary. */
    public function child($studentId)
    {
        $student = $this->childOrFail($studentId);
        $term = Term::current();
        $enrollment = $student->currentEnrollment();

        return response()->json([
            'id'         => $student->id,
            'name'       => $student->full_name,
            'student_id' => $student->admission_number,
            'class'      => $enrollment?->classRoom->name,
            'date_of_birth' => $student->date_of_birth?->toDateString(),
            'status'     => $student->status,
            'balance'    => $student->outstandingBalance(),
            'attendance' => $term ? AttendanceRecord::rateFor($student->id, $term->id) : null,
            // History is the child's own; no other pupil appears in it.
            'history'    => $student->enrollments->sortBy('date_enrolled')->values()
                ->map(fn ($e) => [
                    'academic_year' => $e->academicYear->name,
                    'class'         => $e->classRoom->name,
                    'outcome'       => $e->outcome,
                ]),
        ]);
    }

    /** Fee statement: invoices and payments for this child only. */
    public function statement($studentId, PaymentRecorder $recorder)
    {
        $student = $this->childOrFail($studentId);

        return response()->json($recorder->statement($student));
    }

    /**
     * The child's own attendance.
     *
     * Note what is NOT returned: the class register. A parent seeing who else
     * was absent is a disclosure about other people's children, and there is
     * no reason a parent needs it.
     */
    public function attendance(Request $request, $studentId)
    {
        $student = $this->childOrFail($studentId);

        $termId = $request->query('term_id') ?: Term::current()?->id;

        $records = AttendanceRecord::where('student_id', $student->id)
            ->when($termId, fn ($q) => $q->where('term_id', $termId))
            ->orderByDesc('attended_on')
            ->limit(200)
            ->get();

        $tally = ['P' => 0, 'A' => 0, 'L' => 0, 'E' => 0];
        $records->each(function ($r) use (&$tally) {
            if (isset($tally[$r->state])) {
                $tally[$r->state]++;
            }
        });

        return response()->json([
            'student'  => ['id' => $student->id, 'name' => $student->full_name],
            'states'   => AttendanceRecord::STATES,
            'tally'    => $tally,
            'rate'     => $termId ? AttendanceRecord::rateFor($student->id, $termId) : null,
            'absences' => $records->whereIn('state', ['A', 'L'])->values()
                ->map(fn ($r) => [
                    'date'   => $r->attended_on->toDateString(),
                    'state'  => $r->state,
                    'reason' => $r->reason,
                ]),
        ]);
    }

    /**
     * The report card.
     *
     * Marks are only shown once published. An unpublished mark is a teacher's
     * work in progress — a parent seeing a mark that later changes is worse
     * than a parent waiting a week.
     *
     * The calculator returns each subject's position in class. That is a
     * rank, not another child's mark, so it discloses nothing about anyone
     * else by name.
     */
    public function reportCard(Request $request, $studentId, GradeCalculator $calculator)
    {
        $student = $this->childOrFail($studentId);

        $term = $request->query('term_id')
            ? Term::findOrFail($request->query('term_id'))
            : Term::current();

        if (! $term) {
            return response()->json(['message' => 'No current term is set.'], 422);
        }

        try {
            $card = $calculator->reportCard($student, $term);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json($card);
    }
}

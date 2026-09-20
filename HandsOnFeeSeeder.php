<?php

namespace Database\Seeders;

use App\Models\AcademicYear;
use App\Models\FeeCategory;
use App\Models\FeeStructure;
use App\Models\GradeLevel;
use Illuminate\Database\Seeder;

/**
 * Hands-On Academy fee structure for 2026/2027, from the school's published
 * bill. Nine levels x three terms = 174 fee rows.
 *
 * Items listed in NEW_ONLY are charged to new and re-registering students
 * only. That single flag is what makes the bill's paired Term 1 figures
 * work: Grade 1-3 is 10,500 for a new pupil and 8,400 for a returning one,
 * the difference being registration (500) and uniform (1,600).
 *
 * Three figures on the bill do not reconcile with their own line items —
 * see FEE-NOTES.md. This seeder loads the LINE ITEMS, because those are what
 * an invoice must actually charge row by row. Correct the three and re-run.
 *
 *   php artisan db:seed --class=HandsOnFeeSeeder
 */
class HandsOnFeeSeeder extends Seeder
{
    /** Charged once, on registration or re-registration. */
    private const NEW_ONLY = ['Registration fee', 'Uniform', 'Book bill'];

    /** The entry year of each stage: everyone here is billed as new. */
    private const STAGE_ENTRY = ['ECD Level 1', 'Grade 1', 'Grade 7'];

    public function run(): void
    {
        $year = AcademicYear::current();

        if (! $year) {
            $this->command->error('Set the current academic year first.');
            return;
        }

        $terms = $year->terms()->orderBy('sequence')->get();

        if ($terms->count() < 3) {
            $this->command->error('This year needs three terms before fees can be loaded.');
            return;
        }

        $loaded = 0;

        foreach ($this->schedule() as $row) {
            $level = GradeLevel::firstOrCreate(
                ['name' => $row['level']],
                ['sequence' => $row['sequence']]
            );

            $level->update([
                'is_stage_entry' => in_array($row['level'], self::STAGE_ENTRY, true),
            ]);

            foreach ($row['terms'] as $sequence => $items) {
                $term = $terms->firstWhere('sequence', $sequence);

                if (! $term) {
                    continue;
                }

                foreach ($items as $name => $amount) {
                    $category = FeeCategory::firstOrCreate(
                        ['name' => $name],
                        ['is_recurring' => ! in_array($name, self::NEW_ONLY, true)]
                    );

                    // updateOrCreate so re-running corrects amounts in place
                    // rather than failing on the unique index.
                    FeeStructure::updateOrCreate(
                        [
                            'fee_category_id' => $category->id,
                            'grade_level_id'  => $level->id,
                            'term_id'         => $term->id,
                        ],
                        [
                            'amount'     => $amount,
                            'applies_to' => in_array($name, self::NEW_ONLY, true)
                                ? 'NEW_ONLY'
                                : 'ALL',
                        ]
                    );

                    $loaded++;
                }
            }
        }

        $this->command->info("Loaded {$loaded} fee rows across " . count($this->schedule()) . ' levels.');
        $this->command->warn('Three totals on the bill do not match their line items — see FEE-NOTES.md.');
    }

    /**
     * The bill, transcribed. Amounts are per student, per term, in dalasi.
     */
    private function schedule(): array
    {
        return [
            [
                'level' => "ECD Daycare",
                'sequence' => 1,
                'terms' => [
                    1 => [
                        'Registration fee' => 500,
                        'School fee' => 4000,
                        'Development fee' => 500,
                        'Stationeries' => 500,
                        'Uniform' => 1500,
                        'Exam fee' => 500,
                    ],
                    2 => [
                        'School fee' => 4000,
                        'Development fee' => 500,
                        'Stationeries' => 500,
                        'Exam fee' => 500,
                    ],
                    3 => [
                        'School fee' => 4000,
                        'Development fee' => 500,
                        'Stationeries' => 500,
                        'Exam fee' => 500,
                    ],
                ],
            ],
            [
                'level' => "ECD Level 1",
                'sequence' => 2,
                'terms' => [
                    1 => [
                        'Registration fee' => 500,
                        'School fee' => 4000,
                        'Development fee' => 500,
                        'Extra curricular' => 500,
                        'Stationeries' => 500,
                        'Uniform' => 1500,
                        'Exam fee' => 500,
                    ],
                    2 => [
                        'School fee' => 4000,
                        'Development fee' => 500,
                        'Extra curricular' => 500,
                        'Stationeries' => 500,
                        'Exam fee' => 500,
                    ],
                    3 => [
                        'School fee' => 4000,
                        'Development fee' => 500,
                        'Extra curricular' => 500,
                        'Stationeries' => 500,
                        'Exam fee' => 500,
                    ],
                ],
            ],
            [
                'level' => "ECD Level 2",
                'sequence' => 3,
                'terms' => [
                    1 => [
                        'Registration fee' => 500,
                        'School fee' => 4000,
                        'Development fee' => 500,
                        'Extra curricular' => 500,
                        'Stationeries' => 500,
                        'Uniform' => 1500,
                        'Exam fee' => 500,
                    ],
                    2 => [
                        'School fee' => 4000,
                        'Development fee' => 500,
                        'Extra curricular' => 500,
                        'Stationeries' => 500,
                        'Exam fee' => 500,
                    ],
                    3 => [
                        'School fee' => 4000,
                        'Development fee' => 500,
                        'Extra curricular' => 500,
                        'Stationeries' => 500,
                        'Exam fee' => 500,
                    ],
                ],
            ],
            [
                'level' => "ECD Level 3",
                'sequence' => 4,
                'terms' => [
                    1 => [
                        'Registration fee' => 500,
                        'School fee' => 4000,
                        'Development fee' => 500,
                        'Extra curricular' => 500,
                        'Stationeries' => 500,
                        'Uniform' => 1500,
                        'Exam fee' => 500,
                    ],
                    2 => [
                        'School fee' => 4000,
                        'Development fee' => 500,
                        'Extra curricular' => 500,
                        'Stationeries' => 500,
                        'Exam fee' => 500,
                    ],
                    3 => [
                        'School fee' => 4000,
                        'Development fee' => 500,
                        'Extra curricular' => 500,
                        'Stationeries' => 500,
                        'Exam fee' => 500,
                    ],
                ],
            ],
            [
                'level' => "Grade 1-3",
                'sequence' => 5,
                'terms' => [
                    1 => [
                        'Registration fee' => 500,
                        'School fee' => 4000,
                        'Development fee' => 500,
                        'Extra curricular' => 500,
                        'Book bill' => 1700,
                        'Uniform' => 1600,
                        'Portal fee' => 500,
                        'Abacus' => 700,
                        'Exam fee' => 500,
                    ],
                    2 => [
                        'School fee' => 4000,
                        'Development fee' => 500,
                        'Extra curricular' => 500,
                        'Portal fee' => 500,
                        'Abacus' => 700,
                        'Exam fee' => 500,
                    ],
                    3 => [
                        'School fee' => 4000,
                        'Development fee' => 500,
                        'Extra curricular' => 500,
                        'Portal fee' => 500,
                        'Abacus' => 700,
                        'Exam fee' => 500,
                    ],
                ],
            ],
            [
                'level' => "Grade 4-6",
                'sequence' => 6,
                'terms' => [
                    1 => [
                        'Registration fee' => 500,
                        'School fee' => 4000,
                        'Development fee' => 500,
                        'Extra curricular' => 500,
                        'Book bill' => 1700,
                        'Uniform' => 1600,
                        'Portal fee' => 500,
                        'Lab fee' => 500,
                        'Abacus' => 700,
                        'Exam fee' => 500,
                    ],
                    2 => [
                        'School fee' => 4000,
                        'Development fee' => 500,
                        'Extra curricular' => 500,
                        'Portal fee' => 500,
                        'Lab fee' => 500,
                        'Abacus' => 700,
                        'Exam fee' => 500,
                    ],
                    3 => [
                        'School fee' => 4000,
                        'Development fee' => 500,
                        'Extra curricular' => 500,
                        'Portal fee' => 500,
                        'Lab fee' => 500,
                        'Abacus' => 700,
                        'Exam fee' => 500,
                    ],
                ],
            ],
            [
                'level' => "Grade 7",
                'sequence' => 7,
                'terms' => [
                    1 => [
                        'Registration fee' => 500,
                        'School fee' => 7000,
                        'Development fee' => 500,
                        'Extra curricular' => 500,
                        'Book bill' => 3500,
                        'Uniform' => 2500,
                        'Portal fee' => 500,
                        'Lab fee' => 500,
                        'Exam fee' => 500,
                    ],
                    2 => [
                        'School fee' => 7000,
                        'Development fee' => 500,
                        'Extra curricular' => 500,
                        'Portal fee' => 500,
                        'Lab fee' => 500,
                        'Exam fee' => 500,
                    ],
                    3 => [
                        'School fee' => 7000,
                        'Development fee' => 500,
                        'Extra curricular' => 500,
                        'Portal fee' => 500,
                        'Lab fee' => 500,
                        'Exam fee' => 500,
                    ],
                ],
            ],
            [
                'level' => "Grade 8",
                'sequence' => 8,
                'terms' => [
                    1 => [
                        'Registration fee' => 500,
                        'School fee' => 7000,
                        'Development fee' => 500,
                        'Extra curricular' => 500,
                        'Book bill' => 3500,
                        'Uniform' => 2500,
                        'Portal fee' => 500,
                        'Practical fee' => 1000,
                        'Lab fee' => 500,
                        'Exam fee' => 500,
                    ],
                    2 => [
                        'School fee' => 7000,
                        'Development fee' => 500,
                        'Extra curricular' => 500,
                        'Portal fee' => 500,
                        'Practical fee' => 1000,
                        'Lab fee' => 500,
                        'Exam fee' => 500,
                    ],
                    3 => [
                        'School fee' => 7000,
                        'Development fee' => 500,
                        'Extra curricular' => 500,
                        'Portal fee' => 500,
                        'Practical fee' => 1000,
                        'Lab fee' => 500,
                        'Exam fee' => 500,
                    ],
                ],
            ],
            [
                'level' => "Grade 9",
                'sequence' => 9,
                'terms' => [
                    1 => [
                        'School fee' => 7000,
                        'Development fee' => 500,
                        'External exam fee' => 2500,
                        'Portal fee' => 250,
                        'Practical fee' => 1500,
                        'Lab fee' => 250,
                        'Exam fee' => 500,
                    ],
                    2 => [
                        'School fee' => 7000,
                        'Development fee' => 500,
                        'Portal fee' => 500,
                        'Practical fee' => 1500,
                        'Lab fee' => 500,
                        'Exam fee' => 500,
                    ],
                    3 => [
                        'School fee' => 7000,
                        'Development fee' => 500,
                        'Portal fee' => 500,
                        'Practical fee' => 1500,
                        'Lab fee' => 500,
                        'Exam fee' => 500,
                    ],
                ],
            ],
        ];
    }
}

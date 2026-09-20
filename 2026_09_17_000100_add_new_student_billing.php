<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The published bill charges some items only to new and re-registering
 * students — registration, uniform, and in upper basic the book bill. That
 * is what produces its two Term 1 figures, "New 10500 / Old 8400".
 *
 * My original schema had no way to express this. A fee structure was priced
 * per grade level and term, full stop, so every student in Grade 1-3 would
 * have been charged registration every single year. This adds the missing
 * dimension.
 *
 * Doing it as a column on fee_structures rather than as a discount matters:
 * a returning student is not receiving a 2,100 discount off a 10,500 bill,
 * they were never charged those items at all. Modelling it as a discount
 * would misstate both the billed total and the collection rate.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fee_structures', function (Blueprint $table) {
            $table->string('applies_to', 12)->default('ALL')->after('term_id');
            // ALL | NEW_ONLY   (NEW_ONLY covers new and re-registering students)
        });

        Schema::table('students', function (Blueprint $table) {
            // Set when the student registers or re-registers. Independent of
            // admission_date, because the bill treats the entry year of each
            // stage as a fresh registration even for continuing children.
            $table->boolean('is_new_registration')->default(true)->after('status');
        });

        Schema::table('grade_levels', function (Blueprint $table) {
            // Level 1, Grade 1 and Grade 7 are the entry points into each
            // stage, and the bill says everyone in them is billed as new.
            $table->boolean('is_stage_entry')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('grade_levels', fn (Blueprint $t) => $t->dropColumn('is_stage_entry'));
        Schema::table('students', fn (Blueprint $t) => $t->dropColumn('is_new_registration'));
        Schema::table('fee_structures', fn (Blueprint $t) => $t->dropColumn('applies_to'));
    }
};

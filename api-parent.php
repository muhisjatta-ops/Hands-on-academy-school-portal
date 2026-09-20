<?php

/*
|--------------------------------------------------------------------------
| Parent portal routes
|--------------------------------------------------------------------------
| Paste inside the Route::middleware('two-factor')->group(...) block in
| routes/api.php, alongside the Phase 2 routes.
|
| Note the role middleware on the GROUP, not on each route. A route added to
| this group later inherits the restriction automatically — which is exactly
| the mistake you want the framework to prevent, rather than relying on
| whoever adds it remembering.
*/

use App\Http\Controllers\ParentPortalController;
use Illuminate\Support\Facades\Route;

Route::prefix('parent')
    ->middleware('role:parent')
    ->group(function () {
        Route::get('/children', [ParentPortalController::class, 'children']);
        Route::get('/children/{student}', [ParentPortalController::class, 'child']);
        Route::get('/children/{student}/statement', [ParentPortalController::class, 'statement']);
        Route::get('/children/{student}/attendance', [ParentPortalController::class, 'attendance']);
        Route::get('/children/{student}/report-card', [ParentPortalController::class, 'reportCard']);
    });

/*
| Deliberately absent, and worth stating so nobody adds them later:
|
|   /parent/students          — the school roll. Not a parent's business.
|   /parent/class/{id}        — the class register names other children.
|   /parent/children/{id}/edit — parents do not edit the school's records.
|                                A correction goes through the office, so
|                                there is an audit trail of who changed what.
|
| Note also that {student} is NOT route-model-bound. Binding would let
| Laravel fetch the record before the controller could check whose child it
| is, and a future `Student $student` type-hint would quietly hand over any
| pupil in the school. The controller resolves the id itself, against the
| guardian's own children.
*/

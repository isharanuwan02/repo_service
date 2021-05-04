<?php

use Illuminate\Support\Facades\Route;

/*
  |--------------------------------------------------------------------------
  | API Routes
  |--------------------------------------------------------------------------
  |
  | Here is where you can register API routes for your application. These
  | routes are loaded by the RouteServiceProvider within a group which
  | is assigned the "api" middleware group. Enjoy building your API!
  |
 */


Route::post('/repo/repocreate', [App\Http\Controllers\Repo\RepoController::class, 'createRepo']);
Route::post('/repo/repocommits', [App\Http\Controllers\Repo\RepoController::class, 'createCommit']);
Route::post('/repo/repoissues', [App\Http\Controllers\Repo\RepoController::class, 'createIssues']);
Route::post('/repo/repousers', [App\Http\Controllers\Repo\RepoController::class, 'createRepoUsers']);
Route::post('/repo/repopulls', [App\Http\Controllers\Repo\RepoController::class, 'createRepoPulls']);

//cronjobs
Route::get('/repo/runcron', [App\Http\Controllers\Repo\RepoController::class, 'cronJob']);
//developer iq
Route::post('/repo/deviq', [App\Http\Controllers\Repo\RepoController::class, 'viewDevIq']);


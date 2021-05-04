<?php

namespace App\Http\Controllers\Repo;

use App\Http\Controllers\Controller;
use App\Services\Repo\RepoService;
use Illuminate\Http\Request;

class RepoController extends Controller
{

    private $repoService;

    public function __construct()
    {
        $this->repoService = new RepoService();
    }

    /**
     * {@inheritdoc}
     */
    public function createRepo(Request $request)
    {
        return $this->repoService->createRepo($request);
    }

    /**
     * {@inheritdoc}
     */
    public function createCommit(Request $request)
    {
        return $this->repoService->createCommit($request);
    }

    /**
     * {@inheritdoc}
     */
    public function cronJob()
    {
        return $this->repoService->cronJob();
    }

    /**
     * {@inheritdoc}
     */
    public function createIssues(Request $request)
    {
        return $this->repoService->createIssues($request);
    }

    /**
     * {@inheritdoc}
     */
    public function createRepoUsers(Request $request)
    {
        return $this->repoService->createRepoUsers($request);
    }

    /**
     * {@inheritdoc}
     */
    public function createRepoPulls(Request $request)
    {
        return $this->repoService->createRepoPulls($request);
    }

    /**
     * {@inheritdoc}
     */
    public function viewDevIq(Request $request)
    {
        return $this->repoService->viewDevIq($request);
    }

}

<?php

/**
 * {@inheritdoc}
 */

namespace App\Services\Repo;

use Illuminate\Support\Facades\DB;
use Exception;
use App\Services\Auth\ConnectAuthService;
use App\Models\Repo;
use App\Models\RepoCronjob;
use App\Models\RepoCommit;
use App\Models\RepoIssue;
use App\Models\RepoUser;
use App\Models\RepoPull;
use App\Models\RepoMatric;

class RepoService
{

    private $enumSuccess = 0;

    public function __construct()
    {
        $this->enumSuccess = app('config')->get("enum.common.log_status")['SUCCESS'];
    }

    /**
     * {@inheritdoc}
     */
    public function createRepo($request)
    {
        try {

//            $authApi = new ConnectAuthService();
//            $userData = $authApi->getUserDetails($request);
//
//            if (!permissionLevelCheck('SELLER_ONLY', $userData['role_id'])) {
//                throw new Exception("SELLER_ONLY", getStatusCodes('UNAUTHORIZED'));
//            }
//            $userId = $userData['id'];
//            list of repos to a user - SLIIT-HCI
            $repoOwnerName = $request->ownername;
            $saveData = $this->saveReposdb($repoOwnerName);

            if ($saveData instanceof Exception) {
                throw new Exception($saveData->getMessage(), $saveData->getCode());
            }
            return response()->json([
                        'data' => $saveData,
                        'message' => 'REPO_CREATE_OK'
            ]);
        } catch (Exception $exception) {
            DB::rollBack();
            dd($exception);
            addToLog($exception->getMessage());
            return response()->json(['message' => $exception->getMessage()], $exception->getCode() == 0 ? getStatusCodes('VALIDATION_ERROR') : $exception->getCode());
        }
    }

    public function saveReposdb($repoOwnerName)
    {
        DB::beginTransaction();
        try {
            $client = new \Github\Client();
            $repositories = $client->api('user')->repositories($repoOwnerName);

            $repoNames = [];
            foreach ($repositories as $key => $value) {

                $repoExist = Repo::where('full_name', '=', $value['full_name'])->first();
                if (!$repoExist) {
                    $repoInst = New Repo();
                    $repoInst->git_id = $value['id'];
                    $repoInst->full_name = $value['full_name'];
                    $repoInst->owner_id = $value['owner']['id'];
                    $repoInst->created_at = now();
                    $repoInst->save();
                    DB::commit();
                    $repoNames[] = $value['full_name'];
                }
            }
            addToLog('repos added', $this->enumSuccess);
            return $repoNames;
        } catch (Exception $exception) {
            DB::rollBack();
            dd($exception);
            addToLog($exception->getMessage());
            return response()->json(['message' => $exception->getMessage()], $exception->getCode() == 0 ? getStatusCodes('VALIDATION_ERROR') : $exception->getCode());
        }
    }

    public function createRepoUsers($request)
    {
        try {

//            $authApi = new ConnectAuthService();
//            $userData = $authApi->getUserDetails($request);
//
//            if (!permissionLevelCheck('SELLER_ONLY', $userData['role_id'])) {
//                throw new Exception("SELLER_ONLY", getStatusCodes('UNAUTHORIZED'));
//            }
//            $userId = $userData['id'];
//            list of repos to a user - SLIIT-HCI
            $repoOwnerName = $request->ownername;

            $saveData = $this->save2dbRepoUsers($repoOwnerName);

            if ($saveData instanceof Exception) {
                throw new Exception($saveData->getMessage(), $saveData->getCode());
            }

            return response()->json([
                        'data' => $saveData,
                        'message' => 'USERS_CREATE_OK'
            ]);
        } catch (Exception $exception) {
            DB::rollBack();
            dd($exception);
            addToLog($exception->getMessage());
            return response()->json(['message' => $exception->getMessage()], $exception->getCode() == 0 ? getStatusCodes('VALIDATION_ERROR') : $exception->getCode());
        }
    }

    public function save2dbRepoUsers($repoOwnerName)
    {
        DB::beginTransaction();
        try {
            //            allowed memeber list from github.com
            //repo data from git api
            $client = new \Github\Client();
//            /orgs/{org}/members
            $members = $client->api('members')->all($repoOwnerName);

            $addedUsers = [];
            foreach ($members as $key => $value) {

                $userExist = RepoUser::where('git_user_id', '=', $value['id'])->first();
                if (!$userExist) {
                    $userInst = New RepoUser();
                    $userInst->git_user_id = $value['id'];
                    $userInst->org_name = $repoOwnerName;
                    $userInst->git_user_login = $value['login'];
                    $userInst->created_at = now();
                    $userInst->save();
                    DB::commit();
                    $addedUsers[] = $value['login'];
                }
            }
            addToLog('repo issues added', $this->enumSuccess);

            return $addedUsers;
        } catch (Exception $exception) {
            DB::rollBack();
            dd($exception);
            addToLog($exception->getMessage());
            return response()->json(['message' => $exception->getMessage()], $exception->getCode() == 0 ? getStatusCodes('VALIDATION_ERROR') : $exception->getCode());
        }
    }

    public function createCommit($request)
    {
        try {

//            $authApi = new ConnectAuthService();
//            $userData = $authApi->getUsersDetails($request);
//
//            if (!permissionLevelCheck('SELLER_ONLY', $userData['role_id'])) {
//                throw new Exception("SELLER_ONLY", getStatusCodes('UNAUTHORIZED'));
//            }
//            $userId = $userData['id'];
            //repo data from git api
//            list of repos to a user - SLIIT-HCI
            $repoOwnerName = $request->ownername;
            $repoName = $request->reponame;
            $repoFullName = $repoOwnerName . '/' . $repoName;
            $repoData = $this->getRepoGitId($repoFullName);
            if (!$repoData['status']) {
                throw new Exception("REPO_NOT_FOUND", getStatusCodes('EXCEPTION'));
            }

            $saveData = $this->saveCommits2Db($repoOwnerName, $repoName, $repoData['repo_id']);
            if ($saveData instanceof Exception) {
                throw new Exception($saveData->getMessage(), $saveData->getCode());
            }
            return response()->json([
                        'data' => $saveData,
                        'message' => 'COMMITS_CREATE_OK'
            ]);
        } catch (Exception $exception) {
            DB::rollBack();
            dd($exception);
            addToLog($exception->getMessage());
            return response()->json(['message' => $exception->getMessage()], $exception->getCode() == 0 ? getStatusCodes('VALIDATION_ERROR') : $exception->getCode());
        }
    }

    public function saveCommits2Db($repoOwnerName, $repoName, $repoId)
    {
        DB::beginTransaction();
        try {
            //            list of commits to master branch - committer id
            $client = new \Github\Client();
            $commits = $client->api('repo')->commits()->all($repoOwnerName, $repoName, array('sha' => 'master'));

            $addedCommits = [];
            foreach ($commits as $key => $value) {

                $existingRepoInst = RepoCommit::where('commit_sha', '=', $value['sha'])->first();
                if (!$existingRepoInst) {
                    $repoInst = New RepoCommit();
                    $repoInst->repo_id = $repoId;
                    $repoInst->committer_id = $value['committer']['id'];
                    $repoInst->commited_date = $value['commit']['author']['date'];
                    $repoInst->commit_sha = $value['sha'];
                    $repoInst->created_at = now();
                    $repoInst->save();
                    DB::commit();
                    $addedCommits[] = $value['sha'];
                }
            }
            addToLog('repos added', $this->enumSuccess);
            return $addedCommits;
        } catch (Exception $exception) {
            DB::rollBack();
            dd($exception);
            addToLog($exception->getMessage());
            return response()->json(['message' => $exception->getMessage()], $exception->getCode() == 0 ? getStatusCodes('VALIDATION_ERROR') : $exception->getCode());
        }
    }

    public function createIssues($request)
    {
        try {

//            $authApi = new ConnectAuthService();
//            $userData = $authApi->getUserDetails($request);
//
//            if (!permissionLevelCheck('SELLER_ONLY', $userData['role_id'])) {
//                throw new Exception("SELLER_ONLY", getStatusCodes('UNAUTHORIZED'));
//            }
//            $userId = $userData['id'];
//            list of repos to a user - SLIIT-HCI
            $repoOwnerName = $request->ownername;
            $repoName = $request->reponame;
            $repoFullName = $repoOwnerName . '/' . $repoName;
            $repoData = $this->getRepoGitId($repoFullName);
            if (!$repoData['status']) {
                throw new Exception("REPO_NOT_FOUND", getStatusCodes('EXCEPTION'));
            }

            $saveData = $this->saveIssues2Db($repoOwnerName, $repoName, $repoData['repo_id']);

            if ($saveData instanceof Exception) {
                throw new Exception($saveData->getMessage(), $saveData->getCode());
            }
            return response()->json([
                        'data' => $saveData,
                        'message' => 'ISSUES_CREATE_OK'
            ]);
        } catch (Exception $exception) {
            DB::rollBack();
            dd($exception);
            addToLog($exception->getMessage());
            return response()->json(['message' => $exception->getMessage()], $exception->getCode() == 0 ? getStatusCodes('VALIDATION_ERROR') : $exception->getCode());
        }
    }

    public function saveIssues2Db($repoOwnerName, $repoName, $repoId)
    {
        DB::beginTransaction();
        try {
//            list of opened issues
//            repo data from git api
            $client = new \Github\Client();
            $issues = $client->api('issue')->all($repoOwnerName, $repoName, array('state' => 'closed'));

            $addedIssues = [];
            foreach ($issues as $key => $value) {

                $productInst = RepoIssue::where('issue_id', '=', $value['id'])->first();
                if (!$productInst) {
                    $repoInst = New RepoIssue();
                    $repoInst->repo_id = $repoId;
                    $repoInst->issue_id = $value['id'];
                    if (isset($value['assignee']['login'])) {
                        $repoInst->assignee_id = $value['assignee']['login'];
                    }
                    $repoInst->created_at = now();
                    $repoInst->save();
                    DB::commit();
                    $addedIssues[] = $value['id'];
                }
            }
            addToLog('repo issues added', $this->enumSuccess);
            return $addedIssues;
        } catch (Exception $exception) {
            DB::rollBack();
            dd($exception);
            addToLog($exception->getMessage());
            return response()->json(['message' => $exception->getMessage()], $exception->getCode() == 0 ? getStatusCodes('VALIDATION_ERROR') : $exception->getCode());
        }
    }

    public function createRepoPulls($request)
    {
        try {

//            $authApi = new ConnectAuthService();
//            $userData = $authApi->getUserDetails($request);
//
//            if (!permissionLevelCheck('SELLER_ONLY', $userData['role_id'])) {
//                throw new Exception("SELLER_ONLY", getStatusCodes('UNAUTHORIZED'));
//            }
//            $userId = $userData['id'];
            //repo data from git api
//            list of repos to a user - SLIIT-HCI
            $repoOwnerName = $request->ownername;
            $repoName = $request->reponame;
            $repoFullName = $repoOwnerName . '/' . $repoName;
            $repoData = $this->getRepoGitId($repoFullName);
            if (!$repoData['status']) {
                throw new Exception("REPO_NOT_FOUND", getStatusCodes('EXCEPTION'));
            }
            $saveData = $this->save2DbPulls($repoOwnerName, $repoName, $repoData['repo_id']);

            if ($saveData instanceof Exception) {
                throw new Exception($saveData->getMessage(), $saveData->getCode());
            }
            return response()->json([
                        'data' => $saveData,
                        'message' => 'PULLS_CREATE_OK'
            ]);
        } catch (Exception $exception) {
            DB::rollBack();
            dd($exception);
            addToLog($exception->getMessage());
            return response()->json(['message' => $exception->getMessage()], $exception->getCode() == 0 ? getStatusCodes('VALIDATION_ERROR') : $exception->getCode());
        }
    }

    public function save2DbPulls($repoOwnerName, $repoName, $repoId)
    {
        try {
//            list of opened pulls
            $client = new \Github\Client();
            $openedPulls = $client->api('pull_request')->all($repoOwnerName, $repoName);

            $addedOpenedPulls = [];
            foreach ($openedPulls as $key => $value) {

                $repoPullInst = RepoPull::where('pull_id', '=', $value['id'])->first();
                if (!$repoPullInst) {
                    $repoInst = New RepoPull();
                    $repoInst->repo_id = $repoId;
                    $repoInst->pull_id = $value['id'];
                    $repoInst->pull_owner_id = $value['user']['id'];
                    $repoInst->pull_status = 0;
                    $repoInst->created_at = now();
                    $repoInst->save();
                    DB::commit();
                    $addedOpenedPulls[] = $value['id'];
                }
            }
            addToLog('repo opened pulles added', $this->enumSuccess);

            $closedPulls = $client->api('pull_request')->all($repoOwnerName, $repoName, array('state' => 'closed'));

            $addedClosedPulls = [];
            foreach ($closedPulls as $key => $value) {

                $repoPullCloseInst = RepoPull::where('pull_id', '=', $value['id'])->first();
                if (!$repoPullCloseInst) {
                    $repoInst = New RepoPull();
                    $repoInst->repo_id = $repoId;
                    $repoInst->pull_id = $value['id'];
                    $repoInst->pull_owner_id = $value['user']['id'];
                    $repoInst->pull_status = 1;
                    $repoInst->created_at = now();
                    $repoInst->save();
                    DB::commit();
                    $addedClosedPulls[] = $value['id'];
                }
            }
            addToLog('repo opened pulles added', $this->enumSuccess);

            $addedPulls = ['opened_pulls' => $addedOpenedPulls, 'closed_pulls' => $addedClosedPulls];

            return $addedPulls;
        } catch (Exception $exception) {
            DB::rollBack();
            dd($exception);
            addToLog($exception->getMessage());
            return response()->json(['message' => $exception->getMessage()], $exception->getCode() == 0 ? getStatusCodes('VALIDATION_ERROR') : $exception->getCode());
        }
    }

    public function cronJob()
    {
        try {

            //get cronjobs from db
            $cronRepos = RepoCronjob::where('cron_status', '=', 0)
                    ->get();

            if (sizeof($cronRepos) == 0) {
                throw new Exception("CRONS_NOT_FOUND", getStatusCodes('EXCEPTION'));
            }

            $addedData = [];
            foreach ($cronRepos as $key => $value) {
                $repoOwnerName = $value->repo_owner;
                $repoName = $value->repo_name;
                $cronJobId = $value->id;

                //save repo data
                $saveRepoData = $this->saveReposdb($repoOwnerName);
                if ($saveRepoData instanceof Exception) {
                    throw new Exception($saveRepoData->getMessage(), $saveRepoData->getCode());
                }
                $addedData [] = ['repos' => $saveRepoData];

                $repoFullName = $repoOwnerName . '/' . $repoName;
                $repoData = $this->getRepoGitId($repoFullName);

                //add issues
                $saveIssueData = $this->saveIssues2Db($repoOwnerName, $repoName, $repoData['repo_id']);

                if ($saveIssueData instanceof Exception) {
                    throw new Exception($saveIssueData->getMessage(), $saveIssueData->getCode());
                }
                $addedData[] = ['repo_issues' => $saveIssueData];

                //add repo users
                $saveRepoUserData = $this->save2dbRepoUsers($repoOwnerName);

                if ($saveRepoUserData instanceof Exception) {
                    throw new Exception($saveRepoUserData->getMessage(), $saveRepoUserData->getCode());
                }
                $addedData[] = ['repo_users' => $saveRepoUserData];

                //add repo pull opened and closed
                $savePullData = $this->save2DbPulls($repoOwnerName, $repoName, $repoData['repo_id']);

                if ($savePullData instanceof Exception) {
                    throw new Exception($savePullData->getMessage(), $savePullData->getCode());
                }
                $addedData[] = ['repo_pulls' => $savePullData];

                $saveCommitData = $this->saveCommits2Db($repoOwnerName, $repoName, $repoData['repo_id']);
                if ($saveCommitData instanceof Exception) {
                    throw new Exception($saveCommitData->getMessage(), $saveCommitData->getCode());
                }
                $addedData[] = ['repo_commits' => $saveCommitData];

                //update the cron job status
                $cronUpdate = $this->updateCronStatus($cronJobId);
                if ($cronUpdate instanceof Exception) {
                    throw new Exception($cronUpdate->getMessage(), $cronUpdate->getCode());
                }
            }

            addToLog('cron Job Run ok', $this->enumSuccess);
            DB::commit();
            return response()->json([
                        'data' => $addedData,
                        'message' => 'CRON_JOB_OK'
            ]);
        } catch (Exception $exception) {
            DB::rollBack();
            dd($exception);
            addToLog($exception->getMessage());
            return response()->json(['message' => $exception->getMessage()], $exception->getCode() == 0 ? getStatusCodes('VALIDATION_ERROR') : $exception->getCode());
        }
    }

    public function updateCronStatus($cronId)
    {
        try {
            $cronExist = RepoCronjob::where('id', '=', $cronId)
                    ->first();

            if (!$cronExist) {
                throw new Exception("CRON_NOT_EXIST", getStatusCodes('EXCEPTION'));
            }

            $cronExist->cron_status = 1;
            $cronExist->save();
            DB::commit();

            return $cronExist;
        } catch (Exception $exception) {
            DB::rollBack();
            dd($exception);
            addToLog($exception->getMessage());
            return response()->json(['message' => $exception->getMessage()], $exception->getCode() == 0 ? getStatusCodes('VALIDATION_ERROR') : $exception->getCode());
        }
    }

    public function viewDevIq($request)
    {

        DB::beginTransaction();
        try {

//            $authApi = new ConnectAuthService();
//            $userData = $authApi->getUserDetails($request);
//
//            if (!permissionLevelCheck('SELLER_ONLY', $userData['role_id'])) {
//                throw new Exception("SELLER_ONLY", getStatusCodes('UNAUTHORIZED'));
//            }
//            $userId = $userData['id'];
            $repoOwnerName = $request->ownername;
            $repoName = $request->reponame;
            $repoFullName = $repoOwnerName . '/' . $repoName;
            $repoData = $this->getRepoGitId($repoFullName);
            if (!$repoData['status']) {
                //add to cron table
                $this->addCronJob($repoName, $repoOwnerName);
                throw new Exception("REPO_NOT_FOUND_CHECK_IN_ANOTHER_TIME", getStatusCodes('EXCEPTION'));
            }

            //get repo users
            $repoUsers = RepoUser::where('org_name', '=', $repoOwnerName)
                    ->select(['git_user_id'])
                    ->get();

            if (sizeof($repoUsers) == 0) {
                throw new Exception("REPO_USERS_CANNOT_FIND", getStatusCodes('EXCEPTION'));
            }

            $commitCount = [];
            $issueCount = [];
            $openedPullCount = [];
            $closedPullCount = [];
            foreach ($repoUsers as $key => $value) {
                //get commits for the given repo
                $repoCommits = RepoCommit::where('repo_id', '=', $repoData['repo_id'])
                        ->where('committer_id', '=', $value['git_user_id'])
                        ->select(['id', 'committer_id', 'repo_id'])
                        ->get();
                $commitCount[$value['git_user_id']] = sizeof($repoCommits);

                $repoIssues = RepoIssue::where('repo_id', '=', $repoData['repo_id'])
                        ->where('assignee_id', '=', $value['git_user_id'])
                        ->select(['id', 'assignee_id', 'repo_id', 'issue_id'])
                        ->get();
                $issueCount[$value['git_user_id']] = sizeof($repoIssues);

                //count pulls
                $repoOpenedPulls = RepoPull::where('repo_id', '=', $repoData['repo_id'])
                        ->where('pull_owner_id', '=', $value['git_user_id'])
                        ->select(['id', 'pull_owner_id', 'repo_id', 'pull_status'])
                        ->where('pull_status', '=', 0)
                        ->get();
                $openedPullCount[$value['git_user_id']] = sizeof($repoOpenedPulls);

                $repoClosedPulls = RepoPull::where('repo_id', '=', $repoData['repo_id'])
                        ->where('pull_owner_id', '=', $value['git_user_id'])
                        ->select(['id', 'pull_owner_id', 'repo_id', 'pull_status'])
                        ->where('pull_status', '=', 0)
                        ->get();
                $closedPullCount[$value['git_user_id']] = sizeof($repoClosedPulls);
            }

//            dd($commitCount, $openedPullCount, $closedPullCount, $issueCount);
            //calculate
            $devIqs = [];
            foreach ($repoUsers as $key => $value) {
                $userId = $value['git_user_id'];
                $weightedSum = (0.5 * $commitCount[$userId]) + (1.5 * $openedPullCount[$userId]) + $closedPullCount[$userId] - ( 0.5 * $issueCount[$userId]);
                $precentage = ($weightedSum / 4) * 100;
                $matricExist = RepoMatric::where('user_id', '=', $userId)->first();
                if (!$matricExist) {
                    $saveMatric = new RepoMatric();
                    $saveMatric->user_id = $userId;
                    $saveMatric->score = $precentage;
                    $saveMatric->save();
                    DB::commit();
                } else {
                    $matricExist->score = $precentage;
                    $matricExist->save();
                    DB::commit();

                    //check the date and re-run the cron
                    $metricDate = $matricExist->created_at;
                    $diff = now()->diffInMinutes($metricDate);

                    if ($diff > 1200) {
                        //add to cron table
                        $this->addCronJob($repoName, $repoOwnerName);
                    }
                }

                $devIqs[] = [$userId => $precentage];
            }


            addToLog('repo issues added', $this->enumSuccess);

            return response()->json([
                        'data' => $devIqs,
                        'message' => 'DEV_IQ'
            ]);
        } catch (Exception $exception) {
            DB::rollBack();
            dd($exception);
            addToLog($exception->getMessage());
            return response()->json(['message' => $exception->getMessage()], $exception->getCode() == 0 ? getStatusCodes('VALIDATION_ERROR') : $exception->getCode());
        }
    }

    public function addCronJob($repoName, $repoOwnerName)
    {
        $cronExist = RepoCronjob::where('repo_owner', '=', $repoOwnerName)
                ->where('repo_name', '=', $repoName)
                ->where('cron_status', '=', 0)
                ->first();
        if (!$cronExist) {
            $cronInst = new RepoCronjob();
            $cronInst->repo_name = $repoName;
            $cronInst->repo_owner = $repoOwnerName;
            $cronInst->cron_status = 0;
            $cronInst->save();
            DB::commit();
        }
    }

    public function getRepoGitId($repoFullName)
    {
        $repoInst = Repo::where('full_name', '=', $repoFullName)->first();
        if (!$repoInst) {
            return ['status' => false];
        }

        return ['status' => true, 'repo_id' => $repoInst->git_id];
    }

}

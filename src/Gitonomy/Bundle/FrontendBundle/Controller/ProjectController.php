<?php

namespace Gitonomy\Bundle\FrontendBundle\Controller;

use Symfony\Component\Security\Core\Exception\AccessDeniedException;

use Gitonomy\Bundle\CoreBundle\Entity\User;

/**
 * Controller for project displaying.
 *
 * @author Alexandre Salomé <alexandre.salome@gmail.com>
 */
class ProjectController extends BaseController
{
    /**
     * Displays the project main page
     */
    public function showAction($slug, $reference = 'master')
    {
        $project = $this->getProject($slug);

        return $this->render('GitonomyFrontendBundle:Project:show.html.twig', array(
            'project'   => $project,
            'reference' => $reference
        ));
    }

    /**
     * Displays the last commits
     */
    public function showLastCommitsAction($slug, $reference)
    {
        $project = $this->getProject($slug);

        return $this->render('GitonomyFrontendBundle:Project:showLastCommits.html.twig', array(
            'project'   => $project,
            'reference' => $reference
        ));
    }

    /**
     * Displays a commit.
     */
    public function showCommitAction($slug, $hash)
    {
        $project = $this->getProject($slug);

        $commit = $this
            ->get('gitonomy_core.git.repository_pool')
            ->getGitRepository($project)
            ->getCommit($hash)
        ;

        return $this->render('GitonomyFrontendBundle:Project:showCommit.html.twig', array(
            'project' => $project,
            'commit'  => $commit
        ));
    }

    public function blockNavigationAction($slug, $active)
    {
        $project = $this->getProject($slug);

        $repository = $this
            ->get('gitonomy_core.git.repository_pool')
            ->getGitRepository($project)
        ;

        return $this->render('GitonomyFrontendBundle:Project:blockNavigation.html.twig', array(
            'project'    => $project,
            'repository' => $repository,
            'active'     => $active
        ));
    }

    /**
     * Displays last commit of a project.
     *
     * @todo Separate two cases: the requested revision does not exists and no commit.
     */
    public function blockCommitHistoryAction($slug, $reference = 'master', $limit = 10)
    {
        $project = $this->getProject($slug);

        $revision = $this
            ->get('gitonomy_core.git.repository_pool')
            ->getGitRepository($project)
            ->getRevision($reference)
        ;

        try {
            $revision->getResolved();

            $commits = $revision->getLog($limit)->getCommits();

            return $this->render('GitonomyFrontendBundle:Project:blockCommitHistory.html.twig', array(
                'commits' => $commits,
                'project' => $project
            ));
        } catch (\RuntimeException $e) {
            return $this->render('GitonomyFrontendBundle:Project:blockCommitHistoryEmpty.html.twig', array(
                'project' => $project
            ));
        }
    }

    public function blockBranchesActivityAction($slug)
    {
        $project = $this->getProject($slug);

        $repository = $this
            ->get('gitonomy_core.git.repository_pool')
            ->getGitRepository($project)
        ;

        $references = $repository->getReferences();

        $master = $references->getBranch('master');

        $rows = array();
        foreach ($references->getBranches() as $branch) {
            if ($branch == $master) {
                continue;
            }

            $logBehind = $repository->getLog($branch->getFullname().'..'.$master->getFullname());
            $logAbove = $repository->getLog($master->getFullname().'..'.$branch->getFullname());

            $rows[] = array(
                'branch'           => $branch,
                'above'            => count($logAbove->getCommits()),
                'behind'           => count($logBehind->getCommits()),
                'lastModification' => $branch->getLastModification()
            );
        }

        return $this->render('GitonomyFrontendBundle:Project:blockBranchesActivity.html.twig', array(
            'main' => $master,
            'rows' => $rows
        ));
    }

    /**
     * @return Gitonomy\Bundle\CoreBundle\Entity\Project
     */
    protected function getProject($slug)
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw new AccessDeniedException('You must be connected to access a project');
        }

        $project = $this->getDoctrine()->getRepository('GitonomyCoreBundle:Project')->findOneBySlug($slug);
        if (null === $project) {
            throw $this->createNotFoundException(sprintf('Project with slug "%s" not found', $slug));
        }

        if (!$this->get('security.context')->isGranted('PROJECT_CONTRIBUTE', $project)) {
            throw new AccessDeniedException('You are not contributor of the project');
        }

        return $project;
    }
}

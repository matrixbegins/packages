<?php

/*
 * Copyright (c) Terramar Labs
 *
 * For the full copyright and license information, please view the LICENSE file
 * that was distributed with this source code.
 */

namespace Terramar\Packages\Controller;

use Doctrine\ORM\EntityManager;
use Nice\Application;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Terramar\Packages\Event\PackageEvent;
use Terramar\Packages\Events;
use Terramar\Packages\Plugin\Actions;

class PackageController
{
    public function indexAction(Application $app, Request $request)
    {
        /** @var \Doctrine\ORM\EntityManager $entityManager */
        $entityManager = $app->get('doctrine.orm.entity_manager');

        $packages = $entityManager->getRepository('Terramar\Packages\Entity\Package')
            ->createQueryBuilder('p')
            ->join('p.remote', 'r', 'WITH', 'r.enabled = true')
            ->getQuery()->getResult();

        return new Response($app->get('templating')->render('Package/index.html.twig', array(
                'packages' => $packages,
            )));
    }

    public function editAction(Application $app, $id)
    {
        /** @var \Doctrine\ORM\EntityManager $entityManager */
        $entityManager = $app->get('doctrine.orm.entity_manager');
        $package = $entityManager->getRepository('Terramar\Packages\Entity\Package')->find($id);
        if (!$package) {
            throw new NotFoundHttpException('Unable to locate Package');
        }

        return new Response($app->get('templating')->render('Package/edit.html.twig', array(
                'package' => $package,
                'remotes' => $this->getRemotes($app->get('doctrine.orm.entity_manager')),
            )));
    }


    public function deleteAction(Application $app, Request $request, $id)
    {
        $entityManager = $app->get('doctrine.orm.entity_manager');
        $package = $entityManager->getRepository('Terramar\Packages\Entity\Package')->find($id);
        if (!$package) {
            throw new NotFoundHttpException('Unable to locate Package');
        }

        // delete dependencies/child rows first
        // DELETE row from packages_cloneproject_configurations
        $config = $entityManager->getRepository('Terramar\Packages\Plugin\CloneProject\PackageConfiguration')->findOneBy(array(
                'package' => $id,
            ));

        // echo "\n packages_cloneproject_configurations:: ", $config->getId(), "\t ", $config->getPackage()->getName();

        // DELETE row from packages_gitlab_configurations
        $gitlabConfig = $entityManager->getRepository('Terramar\Packages\Plugin\GitLab\PackageConfiguration')->findOneBy(array(
                'package' => $id,
            ));

        // echo "\n packages_gitlab_configurations:: ", $gitlabConfig->getId(), "\t ", $gitlabConfig->getPackage()->getName();

        // DELETE row from packages_github_configurations
        $gitHubConfig = $entityManager->getRepository('Terramar\Packages\Plugin\GitHub\PackageConfiguration')->findOneBy(array(
                'package' => $id,
            ));

        // echo "\n packages_github_configurations:: ", $gitHubConfig->getId(), "\t ", $gitHubConfig->getPackage()->getName();

        // DELETE row from packages_sami_configurations
        $samiConfig = $entityManager->getRepository('Terramar\Packages\Plugin\Sami\PackageConfiguration')->findOneBy(array(
                'package' => $id,
            ));

        // echo "\n packages_sami_configurations:: ", $samiConfig->getId(), "\t ", $samiConfig->getPackage()->getName();

        // DELETE row from packages_satis_configurations
        $satisConfig = $entityManager->getRepository('Terramar\Packages\Plugin\Satis\PackageConfiguration')->findOneBy(array(
                'package' => $id,
            ));

        // echo "\n packages_satis_configurations:: ", $satisConfig->getId(), "\t ", $satisConfig->getPackage()->getName();

        if($samiConfig){
            $entityManager->remove($samiConfig);
        }

        if($satisConfig){
            $entityManager->remove($satisConfig);
        }

        if($gitHubConfig){
            $entityManager->remove($gitHubConfig);
        }

        if($gitlabConfig){
            $entityManager->remove($gitlabConfig);
        }

        if($config){
            $entityManager->remove($config);
        }

        $packName = $package->getName();
        // finally removing the package entry from master table.
        $entityManager->remove($package);
        $entityManager->flush();

        $request->getSession()
             ->getFlashBag()->add(
                'success',
                'Package ' . $packName . 'deleted successfully!' 
            );


        return new RedirectResponse($app->get('router.url_generator')->generate('manage_packages'));

    }

    public function updateAction(Application $app, Request $request, $id)
    {
        /** @var \Doctrine\ORM\EntityManager $entityManager */
        $entityManager = $app->get('doctrine.orm.entity_manager');
        $package = $entityManager->getRepository('Terramar\Packages\Entity\Package')->find($id);
        if (!$package) {
            throw new NotFoundHttpException('Unable to locate Package');
        }

        $enabledBefore = $package->isEnabled();
        $enabledAfter = (bool) $request->get('enabled', false);

        $package->setName($request->request->get('name'));
        $package->setDescription($request->request->get('description'));
        $package->setEnabled($enabledAfter);

        if ($enabledBefore !== $enabledAfter) {
            $eventName = $enabledAfter ? Events::PACKAGE_ENABLE : Events::PACKAGE_DISABLE;
            $event = new PackageEvent($package);

            /** @var \Symfony\Component\EventDispatcher\EventDispatcherInterface $dispatcher */
            $dispatcher = $app->get('event_dispatcher');
            $dispatcher->dispatch($eventName, $event);
        }

        /** @var \Terramar\Packages\Helper\PluginHelper $helper */
        $helper = $app->get('packages.helper.plugin');
        $helper->invokeAction($request, Actions::PACKAGE_UPDATE, array_merge($request->request->all(), array(
                'id' => $id,
            )));

        $entityManager->persist($package);
        $entityManager->flush();

        return new RedirectResponse($app->get('router.url_generator')->generate('manage_packages'));
    }

    public function toggleAction(Application $app, $id)
    {
        /** @var \Doctrine\ORM\EntityManager $entityManager */
        $entityManager = $app->get('doctrine.orm.entity_manager');
        $package = $entityManager->getRepository('Terramar\Packages\Entity\Package')->find($id);
        if (!$package) {
            throw new NotFoundHttpException('Unable to locate Package');
        }

        $enabledAfter = !$package->isEnabled();
        $package->setEnabled($enabledAfter);

        $eventName = $enabledAfter ? Events::PACKAGE_ENABLE : Events::PACKAGE_DISABLE;
        $event = new PackageEvent($package);

        /** @var \Symfony\Component\EventDispatcher\EventDispatcherInterface $dispatcher */
        $dispatcher = $app->get('event_dispatcher');
        $dispatcher->dispatch($eventName, $event);

        $entityManager->persist($package);
        $entityManager->flush();

        return new RedirectResponse($app->get('router.url_generator')->generate('manage_packages'));
    }

    protected function getRemotes(EntityManager $entityManager)
    {
        return $entityManager->getRepository('Terramar\Packages\Entity\Remote')->findBy(array('enabled' => true));
    }
}

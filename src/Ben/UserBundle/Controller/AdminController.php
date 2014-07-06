<?php

namespace Ben\UserBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Httpfoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Ben\UserBundle\Entity\User;
use Ben\UserBundle\Form\userType;
use JMS\SecurityExtraBundle\Annotation\Secure;
use Ben\UserBundle\Form\profileType;

use Ben\AssociationBundle\Pagination\Paginator;

class AdminController extends Controller
{
    /**
     * @Secure(roles="ROLE_MANAGER")
     */
    public function indexAction()
    {
        $em = $this->getDoctrine()->getManager();
        $groups = $em->getRepository('BenUserBundle:Group')->findAll();
        $entitiesLength = $em->getRepository('BenUserBundle:User')->counter();
        return $this->render('BenUserBundle:admin:index.html.twig', array(
                'groups' => $groups,
                'entitiesLength' => $entitiesLength[1]));
    }

    /**
     * @Secure(roles="ROLE_MANAGER")
     */
    public function ajaxListAction(Request $request)
    {
        $em = $this->getDoctrine()->getManager();
        $searchParam = $request->get('searchParam');
        $template='BenUserBundle:admin:ajax_list.html.twig';
        $entities = $em->getRepository('BenUserBundle:user')->getUsersBy($searchParam);
        $pagination = (new Paginator())->setItems(count($entities), $searchParam['perPage'])->setPage($searchParam['page'])->toArray();
        return $this->render($template, array(
                    'entities' => $entities,
                    'pagination' => $pagination,
                    ));
    }

    /**
     * @Secure(roles="ROLE_MANAGER")
     */
    public function newAction()
    {
        $entity = new User();
        $form = $this->createForm(new userType(), $entity);
        return $this->render('BenUserBundle:admin:new.html.twig', array('entity' => $entity, 'form' => $form->createView()));
    }

    /**
     * @Secure(roles="ROLE_MANAGER")
     */
    public function addAction(Request $request)
    {
        $em = $this->get('fos_user.user_manager');
        $entity = new User();
        $form = $this->createForm(new userType(), $entity);
        $form->bind($request);
        if ($form->isValid()) {
            $em->updateUser($entity, false);
            $entity->getProfile()->getImage()->upload();
            $entity->addGroup($this->container->get('fos_user.group_manager')->findGroupByName('Adhérents'));

            $this->getDoctrine()->getManager()->flush();
            $this->get('session')->getFlashBag()->add('success', "Adhérent ajouté avec succée.");
            return $this->redirect($this->generateUrl('ben_show_user', array('id' => $entity->getId())));
        }
        $this->get('session')->getFlashBag()->add('error', "Il y a des erreurs dans le formulaire soumis !");

        return $this->render('BenUserBundle:admin:new.html.twig', array('entity' => $entity, 'form' => $form->createView()));
    }

    /**
     * @Secure(roles="ROLE_MANAGER")
     */
    public function showAction($id)
    {
        $em = $this->getDoctrine()->getManager();
        $entity = $em->getRepository('BenUserBundle:user')->findUser($id);
        if (!$entity) {
            throw $this->createNotFoundException('Unable to find posts entity.');
        }
        return $this->render('BenUserBundle:admin:show.html.twig', array('entity' => $entity));
    }

    /**
     * @Secure(roles="ROLE_MANAGER")
     */
    public function editAction(User $entity)
    {
        $form = $this->createForm(new userType($type), $entity);
        return $this->render('BenUserBundle:admin:edit.html.twig', array('entity' => $entity, 'form' => $form->createView()));
    }

    /**
     * @Secure(roles="ROLE_MANAGER")
     */
    public function updateAction(Request $request, User $user) {
        $em = $this->get('fos_user.user_manager');
        $form = $this->createForm(new userType(), $user);
        $form->bind($request);
        /* check if user has admin role */
        /*if (array_search('ROLE_ADMIN', $user->getRoles()) !== false ){
            $this->get('session')->getFlashBag()->add('Unauthorized access', "impossible de modifier un super utilisateur de cette interface");
            return $this->redirect($this->generateUrl('ben_users'));
        }*/
        if ($form->isValid()) {
            $em->updateUser($user, false);
            $user->getProfile()->getImage()->manualRemove($user->getProfile()->getImage()->getAbsolutePath());
            $user->getProfile()->getImage()->upload();

            $this->getDoctrine()->getManager()->flush();
            $this->get('session')->getFlashBag()->add('success', "Vos modifications ont été enregistrées.");
            return $this->redirect($this->generateUrl('ben_edit_user', array('id' => $user->getId())));
        }
        $this->get('session')->getFlashBag()->add('error', "Il y a des erreurs dans le formulaire soumis !");
        
        return $this->render('BenUserBundle:admin:edit.html.twig', array('entity' => $user, 'form' => $form->createView()));
    }

    /**
     * @Secure(roles="ROLE_MANAGER")
     */
    public function deleteAction($user)
    {
    	$entity = array();
        return $this->render('BenUserBundle:admin:new.html.twig', array('entity' => $entity));
    }
 
    /**
     * @Secure(roles="ROLE_MANAGER")
     */   
    public function removeUsersAction(Request $request)
    {
        $users = $request->get('users');
        $userManager = $this->get('fos_user.user_manager');
        foreach( $users as $id){
            $user = $userManager->findUserBy(array('id' => $id));
            $userManager->deleteUser($user);
        }
        return new Response('supression effectué avec succès');
    } 

    /**
     * @Secure(roles="ROLE_MANAGER")
     */
    public function enableUsersAction(Request $request, $etat)
    {
        $users = $request->get('users');
        $userManager = $this->get('fos_user.user_manager');
        $etat = ($etat==1);
        foreach( $users as $id){
            $user = $userManager->findUserBy(array('id' => $id));
            $user->setEnabled($etat);
            $userManager->updateUser($user);
        }
        return new Response('1');
    }

    /**
     * @Secure(roles="ROLE_MANAGER")
     */    
    public function setRoleAction(Request $request, $role)
    {
        if($role=='admin') $role='ROLE_ADMIN';
        else if($role=='manager') $role='ROLE_MANAGER';
        else if($role=='author') $role='ROLE_AUTHOR';
        else if($role=='premium') $role='ROLE_PREMIUM';
        else $role='ROLE_USER';
        $users = $request->get('users');
        $userManager = $this->get('fos_user.user_manager');
        foreach( $users as $id){
            $user = $userManager->findUserBy(array('id' => $id));
            $user->removeRole('ROLE_MANAGER');
            $user->removeRole('ROLE_ADMIN');
            $user->removeRole('ROLE_AUTHOR');
            $user->removeRole('ROLE_PREMIUM');
            $user->addRole($role);
            $userManager->updateUser($user);
        }
        return new Response('1');
    }

    /**
     * @Secure(roles="ROLE_MANAGER")
     */    
    public function exportAction()
    {
        $em = $this->getDoctrine()->getEntityManager();
        
        $entities = $em->getRepository('BenUserBundle:user')->getUsers();
        $response = $this->render('BenUserBundle:admin:list.csv.twig',array(
                    'entities' => $entities,
                    ));
         $response->headers->set('Content-Type', 'text/csv');
         $response->headers->set('Content-Disposition', 'attachment; filename="contacts.csv"');

        return $response;
    }


    /**
     * Displays a form to edit an existing profil entity.
     * @Secure(roles="IS_AUTHENTICATED_REMEMBERED")
     */
    public function editMeAction() {
        $user = $this->container->get('security.context')->getToken()->getUser();
        $entity = $user->getProfile();

        if (!$entity) {
            throw $this->createNotFoundException('Unable to find profile entity.');
        }

        $form = $this->createForm(new profileType(), $entity);
        return $this->render('BenUserBundle:myProfile:edit.html.twig', array(
                    'entity' => $entity,
                    'form' => $form->createView(),
                ));
    }


    /**
     * Edits an existing profil entity.
     * @Secure(roles="IS_AUTHENTICATED_REMEMBERED")
     */
    public function updateMeAction(Request $request, \Ben\UserBundle\Entity\profile $profile) {
        $em = $this->getDoctrine()->getManager();
        $form = $this->createForm(new profileType(), $profile);
        $form->bind($request);

        if ($form->isValid()) {
            $em->persist($profile);
            $profile->getImage()->manualRemove($profile->getImage()->getAbsolutePath());
            $profile->getImage()->upload();
               
            $em->flush();
            $this->get('session')->getFlashBag()->add('success', "Vos modifications ont été enregistrées.");
            return $this->redirect($this->generateUrl('ben_profile_edit', array('name' => $profile->getId())));
        }
        $this->get('session')->getFlashBag()->add('error', "Il y a des erreurs dans le formulaire soumis !");

        return $this->render('BenUserBundle:myProfile:edit.html.twig', array(
                    'entity' => $profile,
                    'form' => $form->createView(),
                ));
    }

    /**
     * export to pdf
     * @Secure(roles="ROLE_USER")
     */
    public function toPdfAction($users)
    {
        if(!$users)
            return $this->redirect($this->generateUrl('ben_users'));
        $em = $this->getDoctrine()->getManager();

        if($users != 'all'){
            $users_id = explode(',', $users);
            $entities = $em->getRepository('BenUserBundle:user')->findUserById($users_id);
        }
        else $entities = $em->getRepository('BenUserBundle:user')->findAll();

        $now = new \DateTime;
        $now = $now->format('d-m-Y_H-i');
        $html = $this->renderView('BenUserBundle:admin:badge.html.twig', array(
            'entities' => $entities));

        return new Response(
            $this->get('knp_snappy.pdf')->getOutputFromHtml($html),
            200,
            array(
                'Content-Type'          => 'application/pdf',
                'Content-Disposition'   => 'attachment; filename="file'.$now.'.pdf"'
            )
        );
    }
}
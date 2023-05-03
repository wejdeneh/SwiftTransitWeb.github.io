<?php

namespace App\Controller;

use App\Entity\Ligne;
use App\Form\LigneType;
use App\Entity\MoyenTransport;
use App\Repository\LigneRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Knp\Component\Pager\PaginatorInterface;
use Joli\JoliNotif\Notification;
use Joli\JoliNotif\NotifierFactory;

#[Route('/ligne')]
class LigneController extends AbstractController
{
    #[Route('/', name: 'app_ligne_index', methods: ['GET'])]
    public function index(LigneRepository $ligneRepository, PaginatorInterface $paginator ,  Request $request): Response
    {

        $ligs= $ligneRepository->findAll();
        $m = $paginator->paginate(
            $ligs, /* query NOT result */
            $request->query->getInt('page', 1),
            4
        );

        return $this->render('ligne/index.html.twig', [
            'lignes' =>$m,
            
        ]);
    }

    #[Route('/new', name: 'app_ligne_new', methods: ['GET', 'POST'])]
    public function new(Request $request, LigneRepository $ligneRepository): Response
    {
        $ligne = new Ligne();
        $form = $this->createForm(LigneType::class, $ligne);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $notifier = NotifierFactory::create();

            // Create your notification
             $notification =
                         (new Notification())
                         ->setTitle('Swift Transit')
                         ->setBody('Vous avez ajoutez une ligne')
                         ->setIcon(__DIR__.'/logo.png')
                         
      ;
      $notifier->send($notification);
            $ligneRepository->save($ligne, true);

            return $this->redirectToRoute('app_ligne_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->renderForm('ligne/new.html.twig', [
            'ligne' => $ligne,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_ligne_show', methods: ['GET'])]
    public function show(Ligne $ligne): Response
    {
        return $this->render('ligne/show.html.twig', [
            'ligne' => $ligne,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_ligne_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Ligne $ligne, LigneRepository $ligneRepository): Response
    {
        $form = $this->createForm(LigneType::class, $ligne);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $ligneRepository->save($ligne, true);

            return $this->redirectToRoute('app_ligne_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->renderForm('ligne/edit.html.twig', [
            'ligne' => $ligne,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_ligne_delete', methods: ['POST'])]
    public function delete(Request $request, Ligne $ligne, LigneRepository $ligneRepository): Response
    {
        if ($this->isCsrfTokenValid('delete'.$ligne->getId(), $request->request->get('_token'))) {
            $ligneRepository->remove($ligne, true);
        }

        return $this->redirectToRoute('app_ligne_index', [], Response::HTTP_SEE_OTHER);
    }
}

<?php

namespace App\Controller;


use App\Entity\MoyenTransport;
use App\Repository\MoyenTransportRepository;
use App\Form\MoyenTransportRatingType;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\String\Slugger\SluggerInterface;

use App\Repository\TicketRepository;
use App\Entity\Reservation;
use App\Form\ReservationType;
use App\Repository\ReservationRepository;
use Symfony\Component\HttpFoundation\Request;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use App\Repository\StationRepository;
use App\Form\StationType;
use App\Entity\Station;

use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Doctrine\Persistence\ManagerRegistry;


use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;


use App\Entity\Trajet;
use App\Form\TrajetType;
use App\Repository\TrajetRepository;
use App\Repository\CommuneRepository;
use App\Form\CommuneType;
use App\Entity\Commune;
use App\Entity\User;
use App\Repository\UserRepository;
use App\services\imageUploader;
use App\Form\ClientType;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;

class HomeController extends AbstractController
{
    #[Route('/home', name: 'home')]
    public function index(): Response
    {
        return $this->render('Front/front.html.twig', [
            'controller_name' => 'HomeController',
        ]);
    }
    #[Route('/register', name: 'Inscription')]
    public function register(): Response
    {
        return $this->render('user/register.html.twig');
    }
    #[Route('/{id}/change', name: 'app_edit3', methods: ['GET', 'POST'])]
    public function edit(Request $request, User $user, UserRepository $userRepository,imageUploader $imageUploader): Response
    {
        $form = $this->createForm(ClientType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $file=$form->get('images')->getData();
            if($file){
            $imageFileName = $imageUploader->upload($file);
            $user->setImage($imageFileName);
            }
            $userRepository->save($user, true);

            return $this->redirectToRoute('home', [], Response::HTTP_SEE_OTHER);
        }

        return $this->renderForm('Front/profile.html.twig', [
            'user' => $user,
            'form' => $form,
        ]);
    }
    #[Route('/reserver', name: 'reserver')]
    public function newReservation(Request $request, ReservationRepository $reservationRepository,MailerInterface $mailer,AuthenticationUtils $authenticationUtils,UserRepository $userRepository): Response
    {
        $reservation = new Reservation();
        //$reservation->setDateReservation(new \DateTime('now'));
        $form = $this->createForm(ReservationType::class, $reservation);
        $form->handleRequest($request);
        

        if ($form->isSubmitted() && $form->isValid()) {
            $user= new User();
            $error=$authenticationUtils->getLastAuthenticationError();
            $lastUsername=$authenticationUtils->getLastUsername();
            $user=$userRepository->findOneBy(['username'=>$lastUsername]);
            //$reservation->setHeureDepart($form->get('heure_depart')->getData());
            //$reservation->setHeureArrive($form->get('heure_arrive')->getData());
            $entityManager = $this->getDoctrine()->getManager();
            $reservation->setStatus("En attente");
            $entityManager->persist($reservation);
            $entityManager->flush();
            // Send email notification
            /*$email = (new Email())
            ->from('swift.transit2023@gmail.com')
            ->to('swift.transit2023@gmail.com')
            ->subject('New reservation added')
            ->html('<p>A new reservation has been added.</p>');
            $mailer->send($email);*/
            //$user = $this->getDoctrine()->getRepository(User::class)->find(1);
            $email = (new TemplatedEmail())
                ->from(Address::create('Swift Transit <SwiftTransitPlatform@hotmail.com>'))
                ->to($user->getEmail())
                ->subject('Reservation Information')
                ->text('Sending emails is fun again!')
                ->htmlTemplate('mailing/reservation.html.twig')
                ->context([
                    'reservation' => $reservation,
                    'user' => $user->getPrenom().' '.$user->getNom(),
                    'moyen' => $reservation->getIdMoy()->getTypeVehicule(), // add the moyen attribute
                    'heureDepart' => $reservation->getHeureDepart(), // add the heureDepart attribute
                    'heureArrivee' => $reservation->getHeureArrive(), // add the heureArrivee attribute
                    'typeTicket' => $reservation->getTypeTicket(), // add the status attribute
                    'itineraire' => $reservation->getIdIt()->getPtsDepart() . ' -> ' . $reservation->getIdIt()->getPtsArrive(), // add the itineraire attribute
                ]);
            $mailer->send($email);

            $this->addFlash('success', 'reservation ajouter avec succès!');
            //return $this->redirectToRoute('app_reservation_index', [], Response::HTTP_SEE_OTHER);
        }
        return $this->renderForm('reservation/reserver.html.twig', [
            'reservation' => $reservation,
            'form' => $form,
        ]);
    }

   
    #[Route('/tarifs', name: 'tarif_ticket')]
    public function showListTickets(TicketRepository $ticketRepository): Response
    {
        $tickets = $ticketRepository->findAll();
        return $this->render('ticket/tarif.html.twig', [
            //index.html.twig
            'tickets' => $tickets,
            'controller_name' => 'HomeController',
        ]);
    }

     #[Route('/lignes', name: 'lignes_urbaine' , methods: ['GET'])]
    public function listLignes(MoyenTransportRepository $moyenTransportRepository): Response
     {
         return $this->render('moyen_transport/FrontIndex.html.twig', [
            'moyen_transports' => $moyenTransportRepository->findAll(),
        ]);
     }
 

     #[Route('/star/{id}', name: 'star')]
     public function yourAction(Request $request, $id, ManagerRegistry $doctrine)
 {
     if ($request->isXmlHttpRequest()) {
         // handle the AJAX request
         $data = $request->getContent(); // retrieve the data sent by the client-side JavaScript code
         $repository = $doctrine->getRepository(MoyenTransport::class);
         $moys = $repository->find($id);
         
         if ($moys->getNote() == 0) {
             $moys->setNote($data[6]);
         } else {
             $newNote = ($moys->getNote() + $data[6]) / 2;
             if ($newNote < $moys->getNote()) {
                 $newNote = $moys->getNote();
             }
             $moys->setNote($newNote);
         }
         
         $em=$doctrine->getManager();
         $em->persist($moys);
         $em->flush();
         $bl = $repository->find($id);
         $test=$bl->getNote();
         $response = new Response();//nouvelle instance du response pour la renvoyer a la fonction ajax
         $response->setContent(json_encode($test));//encoder les donnes sous forme JSON et les attribuer a la variable response
         $response->headers->set('Content-Type', 'application/json');
         return $response;//envoie du response
     } 
 }

   /*   #[Route('/lignes/{id}/rate', name: 'moyen_transport_rate' )]
     public function rate(Request $request, MoyenTransport $moyenTransport)
     {
       $form = $this->createForm(MoyenTransportRatingType::class,  $moyenTransport);

       $form->handleRequest($request);
   
       if ($form->isSubmitted() && $form->isValid()) {
           $rating = $form->get('note')->getData();
   
           $moyenTransport->setNote(floatval($rating));
   
           $entityManager = $this->getDoctrine()->getManager();
           $entityManager->persist($moyenTransport);
           $entityManager->flush();
   
           return $this->redirectToRoute('lignes_urbaine', ['id' => $moyenTransport->getId()]);
       }
   
       return $this->render('moyen_transport/rate.html.twig', [
           'moyen_transport' => $moyenTransport,
           'form' => $form->createView(),
       ]);
     } */

     #[Route('/lignes/{id}', name: 'lignes_show' , methods: ['GET'])]
     public function ligne(MoyenTransport $moyenTransport): Response
      {
          return $this->render('moyen_transport/FrontShow.html.twig', [
             'moyen_transport' => $moyenTransport,
         ]);
      }

      
  
   /*  #[Route('/itineraires', name: 'voyager_itineraire')]
    public function listItineraires(): Response
 */


    #[Route('/trajets', name: 'voyager_trajet', methods: ['GET'])]
    public function listTrajet(TrajetRepository $trajetRepository): Response

    {
        return $this->render('trajet/trajetFront.html.twig', [
            'trajets' => $trajetRepository->findAll(),
        ]);
    }

    #[Route('/stations', name: 'voyager_station', methods: ['GET'])]
    public function listTrajets(StationRepository $stationRepository): Response

    {
        $stations = $stationRepository->findAll();
    $stationLongAlts = [];
    foreach ($stations as $station) {
        if ($station->getLongAlt()) {
            $stationLongAlts[] = $station->getLongAlt();
        }
    }
    return $this->render('station/stationFront.html.twig', [
        'stationLongAlts' => $stationLongAlts
    ]);
    }

    
    #[Route('/reclamation', name: 'reclamation')]
    public function listReclamations(): Response
    {
        return $this->render('reclamation/reclamation.html.twig');
    }

    
}

<?php

namespace App\Controller;

use App\Entity\User;
use App\Entity\Reservation;
use App\Entity\Iteneraire;
use App\Entity\Ticket;
use App\Entity\MoyenTransport;
use App\Form\ReservationType;
use App\Repository\ReservationRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Dompdf\Dompdf;
use Dompdf\Options;
use Symfony\Component\Mime\Address;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

#[Route('/reservation')]
class ReservationController extends AbstractController
{
    #[Route('/', name: 'app_reservation_index', methods: ['GET'])]
    public function index(Request $request, PaginatorInterface $paginator, ReservationRepository $reservationRepository): Response
    {
        $reservations = $reservationRepository->orderByDate();
        $reservations = $reservationRepository->orderByDateAndTime();
        $pagination = $paginator->paginate(
            $reservations, /* query NOT result */
            $request->query->getInt('page', 1), /*page number*/
            5 /*limit per page*/
        );
        return $this->render('reservation/index.html.twig', [
            'reservations' =>  $pagination
        ]);
    }

    /*#[Route('/', name: 'app_reservation_index', methods: ['GET'])]
    public function index(ReservationRepository $reservationRepository): Response
    {
        return $this->render('reservation/index.html.twig', [
            'reservations' => $reservationRepository->findAll(),
        ]);
    }*/

    #[Route('/new', name: 'app_reservation_new', methods: ['GET', 'POST'])]
    public function new(Request $request, ReservationRepository $reservationRepository, MailerInterface $mailer,AuthenticationUtils $authenticationUtils,UserRepository $userRepository): Response
    //public function new(Request $request, ReservationRepository $reservationRepository, MailerInterface $mailer): Response

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

                ->from(Address::create('Swift Transit <swiftTransitNew@hotmail.com>'))
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
            return $this->redirectToRoute('app_reservation_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->renderForm('reservation/new.html.twig', [
            'reservation' => $reservation,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_reservation_show', methods: ['GET'])]
    public function show(Reservation $reservation): Response
    {
        return $this->render('reservation/show.html.twig', [
            'reservation' => $reservation,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_reservation_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Reservation $reservation, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(ReservationType::class, $reservation);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            //$reservationRepository->save($reservation, true);
            $entityManager->flush();
            $this->addFlash('success', 'reservation modifier avec succès!');
            return $this->redirectToRoute('app_reservation_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->renderForm('reservation/edit.html.twig', [
            'reservation' => $reservation,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_reservation_delete', methods: ['POST'])]
    public function delete(Request $request, Reservation $reservation, ReservationRepository $reservationRepository): Response
    {
        if ($this->isCsrfTokenValid('delete' . $reservation->getId(), $request->request->get('_token'))) {
            $reservationRepository->remove($reservation, true);
            //$entityManager->remove($reservation);
            //$entityManager->flush();
        }

        $this->addFlash('success', 'reservation supprimer avec succès!');
        return $this->redirectToRoute('app_reservation_index', [], Response::HTTP_SEE_OTHER);
    }


    //PDF Function
    #[Route('/pdf', name: 'ticket_pdf')]
    public function pdf(ReservationRepository $reservationRepository): Response
    {
        // Configuration de dompdf
        $pdfOptions = new Options();
        $pdfOptions->set('defaultFont', 'Arial');

        // initialisation pdf
        $dompdf = new Dompdf($pdfOptions);

        //retreive the events data from the database
        $reservation = $reservationRepository->findAll();
        $imgpath = file_get_contents('Back/img/ticket.jpg');
        $convert = base64_encode($imgpath);
        $img_src = 'data:image/jpeg;base64,' . $convert;

        $imgpath2 = file_get_contents('Back/img/swift.png');
        $convert2 = base64_encode($imgpath2);
        $img_src2 = 'data:image/png;base64,' . $convert2;

        //render the eventst from the database
        $html = $this->renderView('reservation/pdf.html.twig', [
            'reservations' => $reservation,
            'img_src' => $img_src,
            'img_src2' => $img_src2,

        ]);
        //load html
        $dompdf->loadHtml($html);

        //setup the paper format
        $dompdf->setPaper('A4', 'Portrait');

        //render pdf as html content
        $dompdf->render();


        //save pdf as listetickets pdf
        $pdfContent = $dompdf->output();

        // Create a response object
        $response = new Response();

        // Set the content type
        $response->headers->set('Content-Type', 'application/pdf');

        // Set the content of the response to the generated pdf content
        $response->setContent($pdfContent);

        // Set the content disposition header for file download
        $response->headers->set('Content-Disposition', 'attachment;filename="listetickets.pdf"');

        // Return the response object
        return $response;
    }
}

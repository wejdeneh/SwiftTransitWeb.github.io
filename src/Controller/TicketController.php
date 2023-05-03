<?php

namespace App\Controller;

use App\Entity\User;
use Stripe\Stripe;
use Stripe\Customer;
use Stripe\Charge;
use Stripe\Token;
use App\Entity\Reservation;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpClient\HttpClient;
use App\Form\PayementType;
use Doctrine\Persistence\ManagerRegistry;
use App\Entity\Ticket;
use App\Form\TicketType;
use App\Repository\TicketRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Knp\Component\Pager\PaginatorInterface;


#[Route('/ticket')]
class TicketController extends AbstractController
{
    /*#[Route('/', name: 'app_ticket_index', methods: ['GET'])]
    public function index(TicketRepository $ticketRepository): Response
    {
        return $this->render('ticket/index.html.twig', [
            'tickets' => $ticketRepository->findAll(),
        ]);
    }*/
    #[Route('/', name: 'app_ticket_index', methods: ['GET'])]
    public function index(Request $request, PaginatorInterface $paginator, TicketRepository $ticketRepository): Response
    {
        $tickets = $paginator->paginate(
            $ticketRepository->findAll(),
            $request->query->getInt('page', 1),
            5
        );
        return $this->render('ticket/index.html.twig', [
            'tickets' => $tickets,
        ]);
    }

    #[Route('/ticket/{id}/payment', name: 'payement')]
    public function paymentAction($id, TicketRepository $repTicket, ManagerRegistry $doctrine,  Request $request): Response
    {
        $user = $this->getDoctrine()->getRepository(User::class)->find(1);
        $ticket = $this->getDoctrine()->getRepository(Ticket::class)->find($id);
        $prix = $ticket->getPrix();
        $ticketType = $ticket->getNomTicket();
        // Set your secret key: remember to change this to your live secret key in production
        Stripe::setApiKey('sk_test_51Mg75HLu7B1VCpQ0mSqMJ2ucVpFhYufToAINJK1T6932bHEpdiBaD6tMbEDEwA5Aa4Fh1b9WBLhXcb02dhZNLM79008NYLc3Cf');
        // Redirect to a success page
        $form = $this->createForm(PayementType::class);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {

            $data = $form->getData();

            $cardNumber = $data['card_number'];
            $expMonth = $data['exp_month'];
            $expYear = $data['exp_year'];
            $cvc = $data['cvc'];

            // Create a new card in Stripe
            $card = [
                'number' => $cardNumber,
                'exp_month' => $expMonth,
                'exp_year' => $expYear,
                'cvc' => $cvc,
            ];

            // Create a new token in Stripe
            $token = Token::create([
                'card' => $card,
            ]);

            // Use the token to charge the customer's card
            $customer = Customer::create([
                'source' => $token->id,
                'email' => $user->getEmail(), // The customer's email address
            ]);

            // Charge the customer's card
            $charge = Charge::create([
                'amount' => $prix, // the amount to charge in cents
                'currency' => 'usd', // the currency of the charge
                'customer' => $customer->id, // the ID of the customer to charge
            ]);
            $ticket->setStatus("payer");
            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->persist($ticket);
            $entityManager->flush();
            // Update the related reservation status to "Confirmed"
            $reservation = $ticket->getReservation();
            if ($reservation !== null) {
                $reservation->setStatus("Confirmed");
                $entityManager->persist($reservation);
                $entityManager->flush();
            }
            // If the charge is successful, send an SMS notification to the customer using Twilio API
            $twilioAccountSid = 'AC46a24a2b271b750bc6a7af2072b22c2d';
            $twilioAuthToken = '219bc0c8f72233fb6fcab3fddf63e34c';
            $twilioFromNumber = '+16205539102';
            $twilioToNumber = '+21654891319';
            // Instantiate a new HTTP client
            $httpClient = HttpClient::create();
            // Send an SMS message using the Twilio API
            $response = $httpClient->request('POST', 'https://api.twilio.com/2010-04-01/Accounts/' . $twilioAccountSid . '/Messages.json', [
                'auth_basic' => $twilioAccountSid . ':' . $twilioAuthToken,
                'body' => [
                    'From' => $twilioFromNumber,
                    'To' => $twilioToNumber,
                    'Body' => 'Bonjour Hanin, votre paiement a été effectuer avec succès pour ' . $ticketType . ' au prix de ' . $prix . ' dinars. Merci pour votre achat!',
                    //'Body' => 'Good morning Hanin, Your payment has been processed successfully for '.$ticketType.' at a price of '.$prix.' dinars. Thank you for your purchase!',
                ],
            ]);

            if ($response->getStatusCode() === 201) {
                // The message was successfully sent
                $this->addFlash('success', 'votre paiement est effectuer avec succées !');
            } else {
                // There was an error sending the message
                $this->addFlash('error', 'There was an error processing your payment. Please try again later.');
            }
            return $this->redirectToRoute('app_ticket_index');
        }
        return $this->render('ticket/paiement.html.twig', [
            'form' => $form->createView(),
            'id' => $id
        ]);
    }

    #[Route('/searchTicket', name: 'searchTicket', methods: ['GET'])]
    /*  public function search(Request $request, TicketRepository $ticketRepository): JsonResponse
    {
        $searchValue = $request->query->get('searchValue');
    
        // Call a method in the TicketRepository to search for the tickets based on the search value
        $tickets = $ticketRepository->searchTickets($searchValue);
    
        // Return a JSON response with the search results
        return $this->json($tickets, 200, [], ['groups' => 'ticket:read']);
    }*/
    public function searchrepo(Request $request, TicketRepository $ticketRepository, PaginatorInterface $paginator): Response
    {
        $searchValue = $request->query->get('searchValue');

        // Call a method in the TicketRepository to search for the tickets based on the search value
        $resultat = $ticketRepository->searchTicketsby($searchValue);
        $tickets = $paginator->paginate(
            $resultat,
            $request->query->getInt('page', 1),
            5
        );

        return $this->render('ticket/index.html.twig', [
            'tickets' => $tickets,
        ]);
    }


    #[Route('/new', name: 'app_ticket_new', methods: ['GET', 'POST'])]
    public function new(Request $request, TicketRepository $ticketRepository): Response
    {
        $ticket = new Ticket();
        $form = $this->createForm(TicketType::class, $ticket);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $ticketRepository->save($ticket, true);
            $this->addFlash('success', 'ticket ajouter avec succès!');
            return $this->redirectToRoute('app_ticket_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->renderForm('ticket/new.html.twig', [
            'ticket' => $ticket,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_ticket_show', methods: ['GET'])]
    public function show(Ticket $ticket): Response
    {
        return $this->render('ticket/show.html.twig', [
            'ticket' => $ticket,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_ticket_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Ticket $ticket, TicketRepository $ticketRepository): Response
    {
        $form = $this->createForm(TicketType::class, $ticket);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $ticketRepository->save($ticket, true);
            $this->addFlash('success', 'ticket modifier avec succès!');
            return $this->redirectToRoute('app_ticket_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->renderForm('ticket/edit.html.twig', [
            'ticket' => $ticket,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_ticket_delete', methods: ['POST'])]
    public function delete(Request $request, Ticket $ticket, TicketRepository $ticketRepository): Response
    {
        if ($this->isCsrfTokenValid('delete' . $ticket->getId(), $request->request->get('_token'))) {
            $ticketRepository->remove($ticket, true);
        }
        $this->addFlash('success', 'ticket supprimer avec succès!');
        return $this->redirectToRoute('app_ticket_index', [], Response::HTTP_SEE_OTHER);
    }





    /* #[Route('/cart', name: 'app_cart_show')]
    public function getTicketCount(): int
    {
        $entityManager = $this->getDoctrine()->getManager();
        $ticketRepository = $entityManager->getRepository(Ticket::class);
    
        $ticketCount = $ticketRepository->createQueryBuilder('t')
            ->select('COUNT(t.id)')
            ->getQuery()
            ->getSingleScalarResult();
    
        return $ticketCount;
    }*/
}

<?php

namespace App\Controller;

use Dompdf\Dompdf;
use Dompdf\Options;
use App\Entity\User;
use App\Entity\Reclamation;
use App\Form\ReclamationType;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Notifier\NotifierInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use App\Repository\UserRepository;



class ReclamationController extends AbstractController
{

    private $entityManager;
    
    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }


    // #[Route('/reclamations', name: 'app_reclamation')]
    // public function index(Request $request, PaginatorInterface $paginator): Response
    // {
    //     $entityManager = $this->getDoctrine()->getManager();
    //     $repository = $entityManager->getRepository(Reclamation::class);
    //     $query = $repository->createQueryBuilder('c')
    //         ->orderBy('c.id_reclamation', 'DESC');

        
    //     // $data = $this->getDoctrine()->getRepository(Reclamation::class)->findAll();
    //     // return $this->render('reclamation/index.html.twig', [
    //     //     'list' => $data  
    //     $data = $paginator->paginate(     ////jdeeed
    //         $query, // Requête contenant les données à paginer (ici notre requête custom)
    //         $request->query->getInt('page', 1), // Numéro de la page en cours, passé dans l'URL, 1 si aucune page
    //         5 // Nombre de résultats par page
    //     );

    //     return $this->render('reclamation/index.html.twig', [
    //         'list' => $data /////jdeeed
    //     ]);
    // }

   /* #[Route('/reclamations', name: 'app_reclamation')]
public function index(Request $request, PaginatorInterface $paginator): Response
{
    $entityManager = $this->getDoctrine()->getManager();
    $repository = $entityManager->getRepository(Reclamation::class);
    $query = $repository->createQueryBuilder('c')
        ->orderBy('c.id_reclamation', 'DESC');

    // Get the value of the items per page from the request
    $itemsPerPage = $request->query->getInt('itemsPerPage', 5);

    $data = $paginator->paginate( 
        $query,
        $request->query->getInt('page', 1), 
        $itemsPerPage
    );

    // Create an array of available items per page options
    $availableItemsPerPage = [5, 10, 25, 50, 100];

    return $this->render('reclamation/index.html.twig', [
        'list' => $data,
        'itemsPerPage' => $itemsPerPage,
        'availableItemsPerPage' => $availableItemsPerPage,
    ]);
}*/
#[Route('/reclamations', name: 'app_reclamation')]
public function index(Request $request, PaginatorInterface $paginator,AuthenticationUtils $authenticationUtils,UserRepository $userRepository): Response
{
    $user= new User();
   
        $error=$authenticationUtils->getLastAuthenticationError();
        $lastUsername=$authenticationUtils->getLastUsername();
        $user=$userRepository->findOneBy(['username'=>$lastUsername]);
        $userId=$user->getId();
        //$userId = $_SESSION[$user->getId()];
        $entityManager = $this->getDoctrine()->getManager();
        $repository = $entityManager->getRepository(Reclamation::class);
        $query = $repository->createQueryBuilder('c')
            ->leftJoin('c.idUser', 'u')
            ->addSelect('u')
            ->where('u.id = :userId')
            ->setParameter('userId', $userId)
            ->orderBy('c.id_reclamation', 'DESC');
           
   
    $itemsPerPage = $request->query->getInt('itemsPerPage', 5);

    $data = $paginator->paginate( 
        $query,
        $request->query->getInt('page', 1), 
        $itemsPerPage
    );

    // Create an array of available items per page options
    $availableItemsPerPage = [5, 10, 25, 50, 100];

    return $this->render('reclamation/index.html.twig', [
        'list' => $data,
        'itemsPerPage' => $itemsPerPage,
        'availableItemsPerPage' => $availableItemsPerPage,
    ]);
}


    

    #[Route('/reclamation/add', name: 'add_reclamation')]
    public function addreclamation(ManagerRegistry $doctrine,Request $req, NotifierInterface $notifier,UserRepository $userRepository,AuthenticationUtils $authenticationUtils): Response {

        $badWords = ['merde','fuck','shit','con','connart','putain','pute','chier','bitch','bèullshit','bollocks','damn','putin'];
      
        $em = $doctrine->getManager();
        $reclamation = new Reclamation();
        $form = $this->createForm(ReclamationType::class,$reclamation);
        // cree une nouvelle formulaire pour recuperer les recs
        $form->handleRequest($req);
        $user = new User();
        

        if ($form->isSubmitted() && $form->isValid()) {
            $text = $reclamation->getMessage_Rec();
            foreach ($badWords as $word) {
                if (stripos($text, $word) !== false) {
                    $this->addFlash('error', 'Le mot interdit "' . $word . '" a été trouvé dans le texte de la réclamation.');
                    return $this->redirectToRoute('add_reclamation');
                }
            }
           
        $lastUsername = $authenticationUtils->getLastUsername();
        $user= $userRepository->findOneBy(['username'=>$lastUsername]);
       // $id=1;
        //$utilisateur = $this->entityManager->getRepository(User::class)->find($id);
        $reclamation->setIdUser($user);
        $this->entityManager->persist($reclamation);
        // affecter le user au rec
        $this->entityManager->flush();
        // mise a jour

            $em->persist($reclamation);
            // affecter la reclamation kemla lel base
            $em->flush();
            // mise a jour lel bd
            return $this->redirectToRoute('app_reclamation');
        }

        return $this->renderForm('reclamation/ajouterreclamation.html.twig',['form'=>$form]);

}

   


#[Route('/reclamation/update/{id}', name: 'update_reclamation')]
    public function update(Request $req, $id) {
      
      $reclamation = $this->getDoctrine()->getRepository(Reclamation::class)->find($id); 
      $form = $this->createForm(ReclamationType::class,$reclamation);
      $form->handleRequest($req);
    if($form->isSubmitted() && $form->isValid()) {
       
    $id=1;
    $utilisateur = $this->entityManager->getRepository(User::class)->find($id);
    $reclamation->setIdUser($utilisateur);
    $this->entityManager->persist($reclamation);
    $this->entityManager->flush();

    ////////////////////////////////////////////////////

        $em = $this->getDoctrine()->getManager();
        $em->persist($reclamation);
        $em->flush();


       
        return $this->redirectToRoute('app_reclamation');
    }

    return $this->renderForm('reclamation/modifierreclamation.html.twig',[
        'form'=>$form]);

}



#[Route('/reclamation/delete/{id}', name: 'delete_reclamation')]
public function delete($id) {
 
 
   
    $data = $this->getDoctrine()->getRepository(Reclamation::class)->find($id); 

      $em = $this->getDoctrine()->getManager();
      $em->remove($data);
      $em->flush();


     

      return $this->redirectToRoute('app_reclamation');
  }





  #[Route('/reclamation/pdf/{id}', name: 'app_pdfr')]
    public function pdf($id): Response
    {  
        // Configure Dompdf according to your needs
        $pdfOptions = new Options();
        $pdfOptions->set('isRemoteEnabled', true);

        $reclamation = $this->getDoctrine()->getRepository(Reclamation::class)->find($id);

        // Instantiate Dompdf with our options
        $dompdf = new Dompdf($pdfOptions);



        // Retrieve the HTML generated in our twig file
        $html = $this->renderView('reclamation/pdf.html.twig', [
            'reclamation' => [$reclamation]
        ]);


        // Load HTML to Dompdf
        $dompdf->loadHtml($html);

        // (Optional) Setup the paper size and orientation 'portrait' or 'portrait'
        $dompdf->setPaper('A4', 'landscape');


        // Render the HTML as PDF
        $dompdf->render();

        // Output the generated PDF to Browser (force download)
        $output = $dompdf->output();
        $response = new Response($output);
        $response->headers->set('Content-Type', 'application/pdf');
        $response->headers->set('Content-Disposition', 'attachment;filename=mypdf.pdf');
        $response->headers->set('Pragma', 'public');
        $response->headers->set('Cache-Control', 'max-age=0');

        return $response;
    }

}

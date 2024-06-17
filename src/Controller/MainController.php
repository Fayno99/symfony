<?php

namespace App\Controller;

use Symfony\Component\HttpFoundation\Request;
use GuzzleHttp\Client;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Attribute\Route;
use App\Service\ApiService;


class MainController extends AbstractController
{
    private $client;

    private $apiService;

    public function __construct(ApiService $apiService, Client $client)
    {
        $this->apiService = $apiService;
        $this->client = $client;
    }

    #[Route('/main', name: 'app_main')]
    public function index(): Response
    {

        $data = $this->apiService->getDataFromApiIndex();
        return $this->render('main/index.html.twig', [
            'controller_name' => 'MainController',
            'data' => $data,
            dd($data)
        ]);

    }
    #[Route('/companies', name: 'app_companies')]
    public function companies(): Response
    {
        $companiesData = $this->apiService->getDataFromApiCompanies();
        return $this->render('main/companies.html.twig', [
            'controller_name' => 'MainController',
            'companies' => $companiesData,
        ]);
    }
    #[Route('master/{companies_id}', name: 'app_master')]
    public function master(int $companies_id): Response
    {
        $masterData = $this->apiService->getDataFromApiMaster($companies_id);
        return $this->render('main/master.html.twig', [
            'controller_name' => 'MainController',
            'masters' => $masterData,
        ]);
    }
    #[Route('/services', name: 'app_services')]
    public function services(): Response
    {
        $servicesData = $this->apiService->getDataFromApiServices();
        return $this->render('main/services.html.twig', [
            'controller_name' => 'MainController',
            'services' => $servicesData,
        ]);
    }

    #[Route('/timeslot/{interval}/{master}', name: 'app_timeslot')]
    public function timeslot(int $interval, int $master): Response
    {
        $timeSlot = $this->apiService->getDataFromApiTimeSlot($interval,$master);
        return $this->render('main/timeslot.html.twig', [
            'controller_name' => 'MainController',
            'timeSlots' => $timeSlot,
        ]);
    }

    #[Route('/addClient', name: 'app_client')]
    public function addUser(): Response
    {
        return $this->render('main/addClient.html.twig', [
            'controller_name' => 'MainController'

        ]);
    }

    #[Route('/masters/{id}', name: 'master')]
    public function masterId(int $id,  ApiService $apiService, SessionInterface $session): Response
    {

        $url = 'companies_id';
        $data= ['companies_id' => $id];
        $response = $apiService->postRequest($url, $data, $session);

        if (isset($response['next_step']) && $response['next_step'] === 'select_master') {

            return $this->redirectToRoute('app_master', ['companies_id' => $id]);

        }
        return $this->redirectToRoute('app_companies');
    }

    #[Route('/service_id/{id}', name: 'serv')]
    public function serv(int $id,  ApiService $apiService, SessionInterface $session): Response
    {

        $url = 'masters_id';
        $data= ['masters_id' => $id];
        $session->set('masters_id', $id);

        $response = $apiService->postRequest($url, $data, $session );
        if (isset($response['next_step']) && $response['next_step'] === 'select_service') {

            return $this->redirectToRoute('app_services');

        }
        return $this->redirectToRoute('app_services');
    }
    #[Route('/goTooSchedule/{interval}/{id}', name: 'schedule')]
    public function schedule(int $interval, int $id,  ApiService $apiService, SessionInterface $session): Response
    {
        $url = 'works_id';
        $data= ['works_id' => $id];
        $master = $session->get('masters_id');

        $response = $apiService->postRequest($url, $data, $session);
        if (isset($response['next_step']) && $response['next_step'] === 'select_time_slot') {


            return $this->redirectToRoute('app_timeslot', ['interval' => $interval, 'master' => $master]);
        }
        return $this->redirectToRoute('app_timeslot', ['interval' => $interval, 'master' => $master]);
    }

    #[Route('/timeSlot/{startDate}/{endDate}', name: 'timeslots')]
    public function timeSlots(string $startDate, string $endDate,  ApiService $apiService, SessionInterface $session): Response
    {
        $url = 'time_slot';
        $data= ['time_slot'=>'',
                'start_order' => $startDate,
                'stop_order' => $endDate];

        $response = $apiService->postRequest($url, $data, $session);
        if (isset($response['next_step']) && $response['next_step'] === 'enter_user_data') {

            return $this->redirectToRoute('app_client');
        }
        return $this->redirectToRoute('app_companies');
    }

    #[Route('/clientAdd', name: 'clientAdd', methods: ['POST'])]
    public function clientAdd(Request $request, ApiService $apiService, SessionInterface $session): Response
    {
        $url = 'time_slot';
        $data = [
            'client' => '',
            'name' => $request->request->get('name'),
            'email' => $request->request->get('email'),
            'telephone' => $request->request->get('telephone'),
            'motorcycles' => $request->request->get('motorcycles'),
        ];
        $response = $apiService->postRequest($url, $data, $session);

        if (isset($response['message']) && $response['message'] === 'All data collected. Ready to submit order.') {
            $data = ['submitOrder' => ''];
            $submitOrderResponse = $apiService->postRequest($url, $data, $session);

            $session->set('submitOrderResponse', $submitOrderResponse);

            return $this->redirectToRoute('app_submit');
        }
        return $this->redirectToRoute('app_companies');
    }


    #[Route('/finalizing', name: 'app_submit')]
    public function finalizing(Request $request, ApiService $apiService, SessionInterface $session): Response
    {
        $response = $session->get('submitOrderResponse');

        if (isset($response['message']) && $response['message'] === 'All data collected. Ready to submit order.') {
            $finalResponse = $apiService->finalRequest($session);

            return $this->render('main/submit.html.twig', ['response' => $finalResponse]);
        }
        return $this->redirectToRoute('app_companies');
    }




}

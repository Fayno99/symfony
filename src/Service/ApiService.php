<?php
namespace App\Service;

use AllowDynamicProperties;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use App\Controller\MainController;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

#[AllowDynamicProperties]
class ApiService
{
    private $client;
    private $logger;
    private $organizationToken;


    public function __construct(Client $client, LoggerInterface $logger, string $organizationToken)
    {
        $this->client = $client;
        $this->logger = $logger;
        $this->organizationToken = $organizationToken;
    }

    public function getDataFromApiIndex(): array
    {
        return $this->getRequest('http://localhost/api/index');
    }

    public function getDataFromApiCompanies(): array
    {
        return $this->getRequest('http://localhost/api/companies');
    }

    public function getDataFromApiMaster(int $companies_id): array
    {
        return $this->getRequest('http://localhost/api/master/' . $companies_id);
    }

    public function getDataFromApiServices(): array
    {
        return $this->getRequest('http://localhost/api/services');
    }

    public function getDataFromApiTimeSlot(int $interval, int $master): array
    {
        return $this->getRequest('http://localhost/api/time-slots/' . $interval . '/' . $master);
    }


    private function getRequest(string $url): array
    {
        try {
            $response = $this->client->request('GET', $url);
            return json_decode($response->getBody()->getContents(), true);
        } catch (RequestException $e) {
            $this->logger->error('Failed to fetch data from API: ' . $e->getMessage());
            return [];
        }
    }

    public function postRequest(string $url, array $data, SessionInterface $session)
    {
        try {
            if (!$session->has('session_id')) {
                $response = $this->client->post('http://localhost/api/selectData', [
                    'headers' => [
                        'X-Organization-Token' => $this->organizationToken,
                    ],
                    'query' => $data,
                ]);
                $responseData = json_decode($response->getBody()->getContents(), true);
                $sessionToken = $responseData['session_id'] ?? null;

                if (!$sessionToken) {
                    throw new \Exception('Failed to get session token');
                }

                // Збереження session_id у сесії
                $session->set('session_id', $sessionToken);
            } else {
                $sessionToken = $session->get('session_id');
            }

            $response = $this->client->post('http://localhost/api/selectData', [
                'headers' => [
                    'X-Organization-Token' => $this->organizationToken,
                    'X-Session-ID' => $sessionToken,
                ],
                'query' => $data,
            ]);

            $responseData = json_decode($response->getBody()->getContents(), true);
            return $responseData;
        } catch (RequestException $e) {
            $this->logger->error('Failed to post data to API: ' . $e->getMessage());
            return [
                'error' => $e->getMessage(),
                'status_code' => $e->getResponse() ? $e->getResponse()->getStatusCode() : 500,
            ];
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());
            return [
                'error' => $e->getMessage(),
                'status_code' => 500,
            ];
        }
    }

    public function finalRequest(SessionInterface $session)
    {
        $sessionToken = $session->get('session_id');

        if (!$sessionToken) {
            return [ 'headers' => [
                'X-Organization-Token' => $this->organizationToken,
                'X-Session-ID' => $sessionToken,
            ],
                'message' => 'No session token found',
                'data' => null,
            ];
        }

        try {
            $response = $this->client->post('http://localhost/api/submitOrder', [
                'headers' => [
                    'X-Organization-Token' => $this->organizationToken,
                    'X-Session-ID' => $sessionToken,
                ],
            ]);

            $responseData = json_decode($response->getBody()->getContents(), true);
            $session->invalidate();
            return $responseData;

        } catch (\Exception $e) {
            return [ 'headers' => [
                'X-Organization-Token' => $this->organizationToken,
                'X-Session-ID' => $sessionToken,
            ],
                'message' => 'Нема данних для відображення',
                'data' => null,
            ];
        }
    }

}

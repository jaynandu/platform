<?php

namespace Ushahidi\App\DataSource\FrontlineSMS;

/**
 * FrontlineSms Data Providers
 *
 * @author     Ushahidi Team <team@ushahidi.com>
 * @package    DataSource\FrontlineSms
 * @copyright  2013 Ushahidi
 * @license    http://www.gnu.org/copyleft/gpl.html GNU General Public License Version 3 (GPLv3)
 */

use Ushahidi\App\DataSource\CallbackDataSource;
use Ushahidi\App\DataSource\OutgoingAPIDataSource;
use Ushahidi\App\DataSource\Message\Type as MessageType;
use Ushahidi\App\DataSource\Message\Status as MessageStatus;
use Ushahidi\Core\Entity\Contact;
use Log;

class FrontlineSMS implements CallbackDataSource, OutgoingAPIDataSource
{

	protected $config;

	/**
	 * Constructor function for DataSource
	 */
	public function __construct(array $config)
	{
		$this->config = $config;
	}

	public function getName()
    {
		return 'FrontlineSMS';
	}

	public function getId()
	{
		return strtolower($this->getName());
	}

	public function getServices()
	{
		return [MessageType::SMS];
	}

	public function getOptions()
	{
		return array(
			'key' => array(
					'label' => 'Key',
					'input' => 'text',
					'description' => 'The API key',
					'rules' => array('required')
			),
			'secret' => array(
				'label' => 'Secret',
				'input' => 'text',
				'description' => 'Set a secret so that only authorized FrontlineCloud accounts can send/recieve message.
					You need to configure the same secret in the FrontlineCloud Activity.',
				'rules' => array('required')
			)
		);
	}

	/**
	 * Contact type user for this provider
	 */
	public $contact_type = Contact::PHONE;

	// FrontlineSms Cloud api url
	protected $apiUrl = 'https://cloud.frontlinesms.com/api/1/webhook';

	/**
	 * @return mixed
	 */
	public function send($to, $message, $title = "")
	{
		// Prepare data to send to frontline cloud
		$data = array(
			"apiKey" => isset($this->config['key']) ? $this->config['key'] : '',
			"payload" => array(
				"message" => $message,
				"recipients" => array(
					array(
						"type" => "mobile",
						"value" => $to
					)
				)
			)
		);

		// Make a POST request to send the data to frontline cloud

		$client = new \GuzzleHttp\Client();

		try {
			$response = $client->request('POST', $this->apiUrl, [
					'headers' => [
						'Accept'               => 'application/json',
						'Content-Type'         => 'application/json'
					],
					'json' => $data
				]);
			// Successfully executed the request

			if ($response->getStatusCode() === 200) {
				return array(MessageStatus::SENT, $this->tracking_id(DataSource\Message\Type::SMS));
			}

			// Log warning to log file.
			$status = $response->getStatusCode();
			Log::warning('Could not make a successful POST request',
				array('message' => $response->messages[$status], 'status' => $status));
		} catch (\GuzzleHttp\Exception\ClientException $e) {
			// Log warning to log file.
			Log::warning('Could not make a successful POST request',
				array('message' => $e->getMessage()));
		}

		return array(MessageStatus::FAILED, false);
	}

	public function registerRoutes(\Laravel\Lumen\Routing\Router $router)
	{
		$router->post('sms/frontlinesms', 'Ushahidi\App\DataSource\FrontlineSMS\FrontlineSMSController@handleRequest');
		$router->post('frontlinesms', 'Ushahidi\App\DataSource\FrontlineSMS\FrontlineSMSController@handleRequest');
	}

	public function verifySecret($secret)
	{
		if (isset($this->config['secret']) and $secret === $this->config['secret']) {
			return true;
		}

		return false;
	}
}

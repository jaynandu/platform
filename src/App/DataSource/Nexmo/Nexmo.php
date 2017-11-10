<?php

namespace Ushahidi\App\DataSource\Nexmo;

/**
 * Nexmo Data Provider
 *
 * @author     Ushahidi Team <team@ushahidi.com>
 * @package    DataSource\Nexmo
 * @copyright  2013 Ushahidi
 * @license    http://www.gnu.org/copyleft/gpl.html GNU General Public License Version 3 (GPLv3)
 */

use Ushahidi\App\DataSource\CallbackDataSource;
use Ushahidi\App\DataSource\OutgoingAPIDataSource;
use Ushahidi\App\DataSource\Message\Type as MessageType;
use Ushahidi\App\MessageStatus as MessageStatus;
use Ushahidi\Core\Entity\Contact;
use Log;

class Nexmo implements CallbackDataSource, OutgoingAPIDataSource
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
		return 'Nexmo';
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
			'from' => array(
				'label' => 'From',
				'input' => 'text',
				'description' => 'The from number',
				'rules' => array('required')
			),
			'secret' => array(
				'label' => 'Secret',
				'input' => 'text',
				'description' => 'The secret value',
				'rules' => array('required')
			),
			'api_key' => array(
				'label' => 'API Key',
				'input' => 'text',
				'description' => 'The API key',
				'rules' => array('required')
			),
			'api_secret' => array(
				'label' => 'API secret',
				'input' => 'text',
				'description' => 'The API secret',
				'rules' => array('required')
			)
		);
	}

	/**
	 * Client to talk to the Nexmo API
	 *
	 * @var NexmoMessage
	 */
	private $client;

	/**
	 * Sets the FROM parameter for the provider
	 *
	 * @return int
	 */
	public function from()
	{
		// Get provider phone (FROM)
		// Replace non-numeric
		return preg_replace('/\D+/', "", parent::from());
	}

	/**
	 * @return mixed
	 */
	public function send($to, $message, $title = "")
	{
		include_once __DIR__ . '/nexmo/NexmoMessage';

		if (! isset($this->client)) {
			$this->client = new \NexmoMessage($this->_options['api_key'], $this->_options['api_secret']);
		}

		// Send!
		try {
			$info = $this->client->sendText('+'.$to, '+'.preg_replace("/[^0-9,.]/", "", $this->from()), $message);
			foreach ($info->messages as $message) {
				if ($message->status != 0) {
					Log::warning('Nexmo: '.$message->errortext);
					return array(MessageStatus::FAILED, false);
				}

				return array(MessageStatus::SENT, $message->messageid);
			}
		} catch (Exception $e) {
			Log::warning($e->getMessage());
		}

		return array(MessageStatus::FAILED, false);
	}

	public function registerRoutes(\Laravel\Lumen\Routing\Router $router)
	{
		$router->post('sms/nexmo[/]', 'Ushahidi\App\DataSource\Nexmo\NexmoController@handleRequest');
		$router->get('sms/nexmo[/]', 'Ushahidi\App\DataSource\Nexmo\NexmoController@handleRequest');
		$router->post('sms/nexmo/reply', 'Ushahidi\App\DataSource\NexmoController\Nexmo\NexmoController@handleRequest');
		$router->post('nexmo', 'Ushahidi\App\DataSource\Nexmo\NexmoController\Nexmo@handleRequest');
	}
}

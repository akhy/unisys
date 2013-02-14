<?php 

/**
* PHP wrapper class for scraping Unisys data
* 
* @author Akhyar Amarullah
* @link http://github.com/akhyrul/unisys
*/
class Unisys
{
	/**
	 * Base Unisys URL
	 * 
	 * @var string
	 */
	const BASE = 'https://unisys.uii.ac.id/';

	/**
	 * Preserving session_id parameter in Unisys URL
	 * 
	 * @var mixed
	 */
	public $session_id = false;

	/**
	 * Preserving logged in username
	 * 
	 * @var mixed
	 */
	public $username = false;

	/**
	 * Class constructor
	 * loading required library
	 * 
	 * @return void
	 */
	public function __construct()
	{
		$this->CI =& get_instance();
		$this->CI->load->library('curl');
	}

	/**
	 * Scrape defined URL and return the response
	 * 
	 * @param  string $url     URL to scrape
	 * @param  array  $post    POST data embedded in request header
	 * @param  array  $options additional cURL parameters
	 * @return string
	 */
	private function grab($url, $post = array(), $options)
	{
		$session = ($this->session_id !== false) 
			? '?session_id='.$this->session_id 
			: '';
		$default = array(
			CURLOPT_FOLLOWLOCATION => 0,
			CURLOPT_CAINFO         => realpath(APPPATH).'/libraries/certificate',
			CURLOPT_USERAGENT      => 'Mozilla/5.0 (Windows; U; Windows NT 5.1; rv:1.7.3) '
				.'Gecko/20041001 Firefox/0.10.1',
			);

		foreach($default as $key => $val)
		{
			if (! array_key_exists($key, $options))
				$options[$key] = $val;
		}

		$CI =& get_instance();
		$CI->curl->create(Unisys::BASE.$url.$session);
		$CI->curl->options($options);
		$CI->curl->post($post);

		return $this->CI->curl->execute();
	}

	/**
	 * Scrape URL and return only the RESPONSE BODY
	 * 
	 * @param  string $url  URL to scrape
	 * @param  array  $post POST data embedded in request header
	 * @return string
	 */
	public function get_body($url, $post = array())
	{
		return $this->grab($url, $post, array(
			CURLOPT_NOBODY         => 0,
			CURLOPT_HEADER         => 0,
			));
	}

	/**
	 * Scrape URL and return only the RESPONSE HEADER
	 * 
	 * @param  string $url  URL to scrape
	 * @param  array  $post POST data embedded in request header
	 * @return string
	 */
	public function get_header($url, $post = array())
	{
		$header = $this->grab($url, $post, array(
			CURLOPT_NOBODY         => 1,
			CURLOPT_HEADER         => 1,
			CURLOPT_VERBOSE        => 1,
			));

		return $this->parse_header($header);
	}


	/**
	 * Authenticate to Unisys
	 * return $user_id if auth success
	 * return false    if auth failed
	 * 
	 * @param string $user_id  Unisys User ID (NIM)
	 * @param string $password Unisys Password. Sadly, the plain one :(
	 * @return mixed
	 */
	public function auth($user_id, $password)
	{
		$headers = $this->get_header(
			'proseslogin.asp',
			array(
				'user_id'  => $user_id,
				'password' => $password,
			)
			);

		if( ! array_key_exists('Location', $headers) )
		{
			$this->session_id = false;
			return false;
		}

		$location = $headers['Location'];
		$tmp = explode('session_id=', $location);
		if(count($tmp) < 2)
		{
			$this->session_id = false;
			return false;
		}

		$session_id = $tmp[1];
		$this->session_id = $session_id;
		$this->username = $user_id;

		return $user_id;
	}

	/**
	 * Get an array of student data 
	 * from the currently logged in user
	 * 
	 * @param void
	 * @return array
	 */
	public function data()
	{
		if($this->session_id === false) 
			return false;

		$return = array();
		$response = $this->get_body('uii-lia/akademik_status.asp');

		$map = array(
			'No mahasiswa'      => 'nim',
			'Nama'              => 'name',
			'Habis teori'       => 'theory',
			'KKN'               => 'kkn',
			'Konsentrasi studi' => 'konsentrasi',
			'SKS/IP kumulatif'  => 'sks_ipk',
			);

		// Extract
		$matches = array();
		preg_match_all('/<td valign="top">(.*)<\/td>\s*<td valign="top">(.*)<\/td>/m', $response, $matches);
		foreach($matches[1] as $i => $key)
		{
			@$return[$map[$key]] = trim($matches[2][$i]);
		}
		unset($return['']);
		$sks_ipk = explode(' / ', $return['sks_ipk']);
		$return['sks'] = $sks_ipk[0];
		$return['ipk'] = $sks_ipk[1];

		return $return;
	}

	/**
	 * Fetch student photo and save it to the path
	 * return false if user not authenticated 
	 * or file path already exists
	 * 
	 * @param  string  $photo_path full system path to save the photo
	 * @return boolean
	 */
	public function fetch_photo($photo_path)
	{
		if($this->session_id === false) 
			return false;

		if(file_exists($photo_path))
			return false;

		$response = $this->get_body('uii-lia/getfoto.asp');
		file_put_contents($photo_path, $response);

		return true;
	}

	/**
	 * Parse cURL response header data
	 * 
	 * @param  string $header cURL header
	 * @return array 
	 */
	private function parse_header($header)
	{
		$retVal = array ();
		$fields = explode( "\r\n", preg_replace( '/\x0D\x0A[\x09\x20]+/', ' ', $header ) );
		foreach ( $fields as $field )
		{
			if ( preg_match( '/([^:]+):(.+)/m', $field, $match ) )
			{
				$match[1] = preg_replace( '/(?<=^|[\x09\x20\x2D])./e', 'strtoupper("\0")', strtolower( trim( $match[1] ) ) );
				$match[2] = trim( $match[2] );

				if ( isset($retVal[$match[1]]) )
				{
					if ( is_array( $retVal[$match[1]] ) )
					{
						$retVal[$match[1]][] = $match[2];
					}
					else
					{
						$retVal[$match[1]] = array ( $retVal[$match[1]], $match[2] );
					}
				}
				else
				{
					$retVal[$match[1]] = $match[2];
				}
			}
			else if ( preg_match( '/([A-Za-z]+) (.*) HTTP\/([\d.]+)/', $field, $match ) )
			{
				$retVal["Request-Line"] = array (
					"Method"       => $match[1],
					"Request-URI"  => $match[2],
					"HTTP-Version" => $match[3]
				);
			}
			else if ( preg_match( '/HTTP\/([\d.]+) (\d+) (.*)/', $field, $match ) )
			{
				$retVal["Status-Line"] = array (
					"HTTP-Version"  => $match[1],
					"Status-Code"   => $match[2],
					"Reason-Phrase" => $match[3]
				);
			}
		}
		return $retVal;
	}
}
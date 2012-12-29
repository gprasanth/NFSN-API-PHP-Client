NFSN-API-PHP-Client is a simple class for using NearlyFreeSpeech.NET's API.

It uses cURL, supports GET, POST, and PUT requests.

Usage:

    $client = new NFSN-API-PHP-Client("username", "apikey");
    $result = $client->post('/email/domain/setForward', array('forward' => 'alias', 'dest_email' => 'some@gmail.com'));
    if($result['code'] == 200)
        echo 'Success!';
    else
        echo $result['response'];

The above is just an example. Other API features can be used similarly.

It doesn't provide JSON parsing functionality.

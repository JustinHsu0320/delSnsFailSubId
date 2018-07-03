<?php
set_time_limit(0);
ini_set('display_errors', 1);
ini_set("memory_limit", "2048M");
require_once 'aws/aws-autoloader.php';

use Aws\Sns\SnsClient;

$cleanSubId = new CleanSubId;
$topic_arn = $cleanSubId->topicArn;
$nextToken = '';
//$nextToken = '';
$resEndAtt = '';
$enableValFalseCount = 0;
$endpointNotExistsCount = 0;
$processCount = 0;

do {
    $resTopic = $cleanSubId->listSubscriptionsByTopic($nextToken);
    $nextToken = $resTopic['NextToken'];
    echo 'nextToken: ' . $nextToken . "\n";

    foreach ($resTopic['Subscriptions'] as $Subscription) {
        $isCanDel = false;
        $application_token = '';
        $user_data = '';
        $delReason = '';
        $del_type = 0;

        $endpoint_arn = $Subscription['Endpoint'];
        $subscriptionArn = $Subscription['SubscriptionArn'];

        try {
            $resEndAtt = $cleanSubId->client->getEndpointAttributes([
                'EndpointArn' => $Subscription['Endpoint'],
            ])->toArray();

            //如果Enable 是false
            if ($resEndAtt['Attributes']['Enables'] == 'false') {
                $isCanDel = true;
                $del_type = 1;
                $enableValFalseCount++;
                $application_token = $resEndAtt['Attributes']['token'];
                $user_data = $resEndAtt['Attributes']['CustomUserData'];
            }
        } catch (Exception $e) {
            //確認是找不到這個 endpoint
            $isNotExist = strpos($e->getMessage(), 'Endpoint does not exist');

            //如果沒道endpoint
            if ($isNotExist) {
                $isCanDel = true;
                $del_type = 2;
                $endpointNotExistsCount++;
            }
        }

        //如果可以刪
        if ($isCanDel) {
            //從topic 裡反訂閱
            $resUnsubscribe = $cleanSubId->client->unsubscribe([
                'SubscriptionArn' => $subscriptionArn, //REQUIRED
            ]);

            //如果是enabled 為false
            if ($del_type == 1) {
                //從application 刪endpoint
                $resDelEnpoint = $cleanSubId->client->deleteEndpoint([
                    'EndpointArn' => $endpoint_arn,
                ]);
            }
        }

        $processCount++;
        echo 'False: ' . $enableValFalseCount . ', Endpoint Not Exists: ' . $endpointNotExistsCount . ', Total Del: ' . ($enableValFalseCount + $endpointNotExistsCount) . ', Process Count: ' . ($processCount). "\n";
    }
} while ($nextToken != '');

echo 'done, done, done.';

class CleanSubId
{
    public $client;
    public $topicArn = 'arn:aws:sns:us-east-1:596552985807:SETN-V2-APP-Production';
    // public $topicArn = 'arn:aws:sns:us-east-1:596552985807:SETN-V2-APP-Develop';

    public function __construct()
    {
        $this->client = new SnsClient([
			'region' => 'us-east-1', 
			'version' => '2010-03-31',
			'credentials' => [
				'key' => 'AKIAIVRNPJL46UM6G7QA',
				'secret' => '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',
			]
		]);
		
		// Wade原著
        // $this->client = SnsClient::factory(array(
        //     'version' => 'latest',
        //     'region'  => 'us-east-1',
        //     'credentials' => array(
        //         'key' => 'AKIAIVRNPJL46UM6G7QA',
        //         'secret' => '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',
        //     )
        // ));
    }

    public function listSubscriptionsByTopic($nextToken = '')
    {
        $result = $this->client->listSubscriptionsByTopic([
            'NextToken' => $nextToken,
            'TopicArn' => $this->topicArn, // REQUIRED
        ])->toArray();

        return $result;
    }
}

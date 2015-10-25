<?php
namespace BlueHerons\GroupMe\Bots;

use \BlueHerons\Cycle\Cycle;
use \DateTime;
use \ReflectionClass;
use \stdClass;

class ResWueBot extends HeronsBot {

    public function __construct($token, $bot_id) {
        parent::__construct($token, $bot_id);

        $this->registerCommand("guardians",  array($this, "guardians"), "PM a list of marked Guardian portals");
    }

    public function guardians() {
        $response = $this->request($this->config->reswue->endpoints->marked_portals);
        $assignee = "Guardian Alerts";
        $count = 0;
        $guardians = array();  

        if (!is_array($response)) {
            error_log("Invalid Response: " . print_r($response, true));
            die();
        }

        foreach ($response as $alert) {
            if ($alert->assignee->name == $assignee && empty($alert->resolvedDate)) {
                error_log(json_encode($alert));
                $count++;
                $guardians[] = $alert;
            }
        }

        usort($guardians, function($a, $b) {
            return strcasecmp($a->comment, $b->comment);
        });

        $this->replyToSender(sprintf("%d guardians currently marked in RESWUE:", $count));
        $message = "";
        for ($i = 0; $i < sizeof($guardians); $i++) {
            if (($i != 0) && ($i % 5 == 0)) {
                $this->replyToSender($message);
                $message = "";
            }

            $message .= $guardians[$i]->comment . "\n";
            $message .= $guardians[$i]->portal->name . "\n";
            $message .= $this->getPortalLink($guardians[$i]->portal) . "\n\n";
        }
        return $message;
    }

    /**
     * Gets link to a portal
     */
    private function getPortalLink($portal) {
        return sprintf('https://ingress.com/intel?ll=%1$s,%2$s&pll=%1$s,%2$s', $portal->lat, $portal->lng);
    }

    /**
     * Retrieves data from RESWUE API
     *
     * @param string $endpoint API Endpoint
     */
    private function request($endpoint) {
        $query_params = array();
        foreach ($this->config->reswue->query as $query) {
            $query_params[$query->name] = htmlentities($query->value);
        }

        $query_string = http_build_query($query_params);
        
        $url = sprintf("%s/%s/%s?%s", $this->config->reswue->url, 
                                      $this->getQueryValue($this->config->reswue->fields->base),
                                      $endpoint,
                                      $query_string);

        error_log($url);

        $cookies = "";
        foreach ($this->config->reswue->cookies as $cookie) {
            $cookies .= $cookie->name . "=" . $cookie->value . "; ";
        }

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_COOKIE, $cookies);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $output = curl_exec($ch);
        error_log($output);

        $data = json_decode(utf8_decode($output));
        if (property_exists($data, "errorCode")) {
            error_log($data->message);
            die();
        }

        return $data;
    }

    private function getQueryValue($field) {
        foreach($this->config->reswue->query as $query) {
            if ($query->name == $field) {
                return $query->value;
            }
        }

        return "";
    }

}
?>

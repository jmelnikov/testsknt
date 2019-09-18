<?php


class CIndex
{
    private $mysql, $user_id, $service_id, $tarifs, $new_tarif;

    public function __destruct()
    {
        $this->mysql->close();
    }

    /**
     * @return bool
     */
    private function SQL_connect(): bool
    {
        require_once('db_cfg.php');
        $this->mysql = new mysqli(DB_HOST, DB_USER, DB_PASSWORD, DB_NAME);

        if ($this->mysql->connect_error) {
            return false;
        }

        return true;
    }

    /**
     * @param string $route
     * @return bool
     */
    public function router(): bool
    {
        $route = $_SERVER['REQUEST_URI'];

        $matches = [];
        if (!preg_match('/.*\/(users\/[0-9]+\/services\/[0-9]+\/tarifs?$)/iu', $route, $matches)) {
            return false;
        }

        $uri = explode('/', $matches[1]);

        $this->user_id = $uri[1];
        $this->service_id = $uri[3];

        return true;
    }

    /**
     * @return bool
     */
    public function prepare_data(): bool
    {
        if (!$this->SQL_connect()) {
            return false;
        }

        $query = 'SELECT `id`, `title`, `price`, `pay_period`, `speed`, `pay_period`, `link`
                  FROM `tarifs`
                  WHERE tarifs.tarif_group_id =
                    (SELECT `tarif_group_id` FROM `tarifs` WHERE tarifs.id =
                        (SELECT services.tarif_id FROM services WHERE services.user_id = ' . $this->user_id . ' AND services.ID = ' . $this->service_id . '))';

        $result = $this->mysql->query($query);
        if (!$result) {
            return false;
        }

        $tarifs = [];
        while ($row = $result->fetch_assoc()) {
            $tarifs[] = $row;
        }

        foreach ($tarifs as $key => $value) {
            $tarifs[$key]['price'] = number_format($tarifs[$key]['price'], 0, '', '');
            $tarifs[$key]['new_payday'] = strtotime('+' . $tarifs[$key]['pay_period'] . ' month') . date('O');
        }

        $this->tarifs = $tarifs;

        $putData = json_decode(file_get_contents('php://input'), true);

        $this->new_tarif = $putData['tarif_id'] ?? null;

        return true;
    }

    /**
     * @return string
     */
    public function get_user_tarifs(): string
    {
        $return = [];

        $return['result'] = 'ok';
        $return['tarifs']['title'] = $this->tarifs[0]['title'];
        $return['tarifs']['link'] = $this->tarifs[0]['link'];
        $return['tarifs']['speed'] = $this->tarifs[0]['speed'];
        $return['tarifs']['tarifs'] = $this->tarifs;

        return json_encode($return);
    }

    /**
     * @return bool
     */
    public function set_user_tarif(): bool
    {
        if(!$this->new_tarif) {
            return false;
        }
        // Ищем тариф среди выбранного списка
        $newTarif = false;
        foreach ($this->tarifs as $tarif) {
            if ($tarif['id'] == $this->new_tarif) {
                $newTarif = $tarif;
                break;
            }
        }

        if($newTarif === false) {
            return false;
        }

        // Устанавливаем новый тариф пользователя
        $query = 'UPDATE `services`
                    SET `tarif_id` = ' . $newTarif['id'] . ', `payday` = \'' . date('Y-m-d', strtotime('+' . $newTarif['pay_period'] . ' month')) . '\'
                    WHERE `user_id` = ' . $this->user_id . ' AND `id` = ' . $this->service_id;
        $result = $this->mysql->query($query);

        if (!$result) {
            return false;
        }

        return true;
    }
}
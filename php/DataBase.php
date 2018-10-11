<?php

/**
 * Created by PhpStorm.
 * User: Юлия
 * Date: 03.01.2018
 * Time: 2:31
 */
class DataBase
{
    private $db;
    public $error;
    public function __construct($host, $username, $password, $database, $port=3306)
    {
        $this->db = new mysqli($host, $username, $password, $database, 3306);
        if ($this->db->connect_errno)
        {
            $this->error = "Не удалось подключиться к MySQL: (" . $this->db->connect_errno . ") " . $this->db->connect_error;
            // echo "Не удалось подключиться к MySQL: (" . $this->db->connect_errno . ") " . $this->db->connect_error;
        }
        $this->db->set_charset("utf8");
    }

    public function select_authorization_info($login) {
        $sql = "SELECT email, password, name, surname, user_id, type, active FROM user WHERE email = '".$this->db->real_escape_string($login)."';";
        $query = $this->db->query($sql);
        return $this->query_handler($query);
    }

    public function select_authorization_token($uid) {
        $sql = "SELECT token FROM user WHERE user_id = '".$this->db->real_escape_string($uid)."';";
        $query = $this->db->query($sql);
        return $this->query_handler($query);
    }

    public function update_authorization_token($uid, $token) {
        $sql = "UPDATE user SET token = '".$this->db->real_escape_string($token)."' WHERE user_id = '".$this->db->real_escape_string($uid)."';";
        $query = $this->db->query($sql);
        // return $this->query_handler($query);
        return $query;
    }
    
    public function delete_authorization_token($uid) {
        $sql = "UPDATE user SET token = NULL WHERE user_id = '".$this->db->real_escape_string($uid)."';";
        $query = $this->db->query($sql);
        // return $this->query_handler($query);
        return $query;
    }

    public function select_deals_list($user_id, $type) {
        // $type может быть archive, new, current
        // список ФИО и id сделок
        switch($type) {
            case "new":
                $range_min = 1;
                $range_max = 3;
                break;
            case "current":
                $range_min = 4;
                $range_max = 7;
                break;
            case "archive":
                $range_min = 8;
                $range_max = 9;
                break;
            default:
                return ['error'=>'Неизвестный тип сделки'];
        }
        // if (strnatcasecmp($type, "new") == 0){
        // } elseif (strnatcasecmp($type, "current") == 0) {
        // } elseif (strnatcasecmp($type, "archive") == 0) {
        // } else {
        //    // echo "Тип сделки неопознан";
        //     return ['error'=>'Unknown type of deal'];
        // }
        $sql = "SELECT client.surname AS surname, client.name AS name, client.father_name AS father_name, deal.deal_id AS deal_id
          FROM client JOIN deal on deal.client_id = client.client_id JOIN user on user.user_id = deal.user_id 
          WHERE user.user_id = ".$user_id." AND deal.status <= ".$range_max." AND deal.status >= ".$range_min.";";
        $query = $this->db->query($sql);
        return $this->query_handler($query);
    }

    public function select_deal_info($deal_id)
    {
        $sql = "SELECT address, square_m, commentary, rooms_number, date, commission, status+0 AS status FROM deal WHERE deal_id = ".$deal_id.";";
        $query = $this->db->query($sql);
        return $this->query_handler($query);
    }

    public function select_user_id_by_deal_id($deal_id)
    {
        $sql = "SELECT deal.user_id FROM deal WHERE deal_id = ".$deal_id.";";
        $query = $this->db->query($sql);
        return $this->query_handler($query);
    }

    public function select_client_info_by_deal_id($deal_id)
    {
        $sql = "SELECT client.surname AS surname, client.name AS name, client.father_name AS father_name, 
          client.phone AS phone, client.email AS client_email FROM client JOIN deal on client.client_id = deal.client_id 
          WHERE deal_id = ".$deal_id.";";
        $query = $this->db->query($sql);
        return $this->query_handler($query);
    }

    public function select_client_info_by_email($email)
    {
        $email = $this->db->real_escape_string($email);
        $sql = "SELECT surname, name, father_name, phone, client_id FROM client WHERE email = '".$email."';";
        $query = $this->db->query($sql);
        return $this->query_handler($query);
    }

    public function select_deal_files_dir($deal_id)
    {
        $sql = "SELECT files FROM deal WHERE deal_id = ".$deal_id.";";
        $query = $this->db->query($sql);
        return $this->query_handler($query);
    }

    public function select_user_list()
    {
        $sql = "SELECT user_id, email, name, surname, father_name, phone, type, active FROM user;";
        $query = $this->db->query($sql);
        return $this->query_handler($query);
    }

    public function select_account_info($user_id)
    {
        $sql = "SELECT user_id, email, name, surname, father_name, phone, type, active FROM user WHERE user_id = ".$user_id.";";
        $query = $this->db->query($sql);
        return $this->query_handler($query);
    }

    public function select_user_type($user_id)
    {
        $sql = "SELECT type FROM user WHERE user_id = ".$user_id.";";
        $query = $this->db->query($sql);
        return $this->query_handler($query);
    }

    public function select_deal_status($deal_id) // сделка удаляется либо админом, либо пользователем, если статус сделки = 1
    {
        $sql = "SELECT status+0 AS status FROM deal WHERE deal_id = ".$deal_id.";";
        $query = $this->db->query($sql);
        return $this->query_handler($query);
    }

    public function select_full_statistics() {
        $sql = "SELECT deal.deal_id, 
            user.surname AS u_surname, user.name AS u_name, user.father_name AS u_father_name, user.phone AS u_phone, user.email AS u_email,
            client.surname AS c_surname, client.name AS c_name, client.father_name AS c_father_name, client.phone AS c_phone, client.email AS c_email,
            deal.address, deal.square_m, deal.rooms_number, deal.date, deal.status+0 AS status, deal.commentary, deal.commission 
            FROM client JOIN deal ON client.client_id = deal.client_id JOIN user ON user.user_id = deal.user_id;";
        $query = $this->db->query($sql);
        return $this->query_handler($query);        
    }

    public function select_user_statistics($user_id) {
        $sql = "SELECT deal.deal_id, 
            user.surname AS u_surname, user.name AS u_name, user.father_name AS u_father_name, user.phone AS u_phone, user.email AS u_email,
            client.surname AS c_surname, client.name AS c_name, client.father_name AS c_father_name, client.phone AS c_phone, client.email AS c_email,
            deal.address, deal.square_m, deal.rooms_number, deal.date, deal.status+0 AS status, deal.commentary, deal.commission 
            FROM client JOIN deal ON client.client_id = deal.client_id JOIN user ON user.user_id = deal.user_id 
            WHERE user.user_id = ".$this->db->real_escape_string($user_id).";";
        $query = $this->db->query($sql);
        return $this->query_handler($query);        
    }

    public function create_new_deal($user_id, $deal_info, $client_info)
    {
        $deal_info = $this->escape_string_array($deal_info);
        $client_info = $this->escape_string_array($client_info);
        $check_client_sql = "SELECT client_id FROM client WHERE email = '".$client_info['email']."';";
        $query = $this->db->query($check_client_sql);
        $client_id_result = $this->query_handler($query);
        if (count($client_id_result) == 0)
        {
            $client_id = $this->insert_client($client_info)[0]['client_id'];
        } else {
            $client_id = $client_id_result[0]['client_id'];
        }
        return $this->insert_deal($deal_info, $user_id, $client_id);
    }

    public function create_new_user($user_info)
    {
        $user_info = $this->escape_string_array($user_info);
        $head_new_user_sql = "INSERT INTO user (email";
        $tail_new_user_sql = " VALUES ('".$user_info['email']."'";
        foreach ($user_info as $key => $info) {
            if ($key != '' && $key != 'email') {
                $head_new_user_sql = $head_new_user_sql . ",".$key;
                $tail_new_user_sql = $tail_new_user_sql . ",'" . $info . "'";
            }
        }
        $new_user_sql = $head_new_user_sql.")".$tail_new_user_sql.");";
        $get_user_id_sql = "SELECT user_id FROM user WHERE email = '".$user_info['email']."';";
        $query = $this->db->query($new_user_sql);
        $query = $this->db->query($get_user_id_sql);
        return $this->query_handler($query);
    }

    public function if_user_exists($email)
    {
        $email = $this->db->real_escape_string($email);
        $sql = "SELECT user_id FROM user WHERE email = '".$email."';";
        $query = $this->db->query($sql);
        if (!$query)
        {
            return ['error' => $this->db->error];
        }
        else {
            if ($query->num_rows === 0)
            {
                return False;
            }
            else {
                return True;
            }
        }
    }

    public function if_client_exists($email)
    {
        $email = $this->db->real_escape_string($email);
        $sql = "SELECT client_id FROM client WHERE email = '".$email."';";
        $query = $this->db->query($sql);
        if (!$query)
        {
            return ['error' => $this->db->error];
        }
        else {
            if ($query->num_rows === 0)
            {
                return False;
            }
            else {
                return $query->fetch_assoc()['client_id'];
            }
        }
    }

    public function query_handler($query)
    {
        if (!$query)
        {
            return ['error' => $this->db->error];
        }
        else {
            if ($query->num_rows === 0)
            {
                // echo 'No results';
                return [];
            }
            else {
                return $query->fetch_all(MYSQLI_ASSOC);
            }
        }
    }

    public function insert_client($client_info)
    {
        $client_info = $this->escape_string_array($client_info);
        $head_new_client_sql = "INSERT INTO client (name, surname";
        $tail_new_client_sql = " VALUES ('".$client_info['name']."','".$client_info['surname']."'";
        foreach (array_keys($client_info) as $key) {
            if (strcmp($client_info[$key], '') != 0 and strcmp($key, 'surname') != 0 and strcmp($key, 'name') != 0) {
                $head_new_client_sql = $head_new_client_sql . ",".$key;
                $tail_new_client_sql = $tail_new_client_sql . ",'" . $client_info[$key] . "'";
            }
        }
        $new_client_sql = $head_new_client_sql.")".$tail_new_client_sql.");";
        $get_client_id_sql = "SELECT client_id FROM client WHERE email = '".$client_info['email']."';";
        $query = $this->db->query($new_client_sql);
        $query = $this->db->query($get_client_id_sql);
        return $this->query_handler($query);
    }

    public function insert_deal($deal_info, $user_id, $client_id)
    {
        $deal_info = $this->escape_string_array($deal_info);
        $head_new_deal_sql = "INSERT INTO deal (user_id, client_id";
        $tail_new_deal_sql = " VALUES (".$user_id.",".$client_id;
        foreach (array_keys($deal_info) as $key) {
            if (strcmp($deal_info[$key], '') != 0) {
                if (is_string($deal_info[$key])) {
                    $head_new_deal_sql = $head_new_deal_sql . "," . $key;
                    $tail_new_deal_sql = $tail_new_deal_sql . ",'" . $deal_info[$key] . "'";
                } else {
                    $head_new_deal_sql = $head_new_deal_sql . "," . $key;
                    $tail_new_deal_sql = $tail_new_deal_sql . "," . $deal_info[$key];
                }
            }
        }
        $new_deal_sql = $head_new_deal_sql.")".$tail_new_deal_sql.");";
        $get_deal_id_sql = "SELECT LAST_INSERT_ID() AS deal_id;";
        $this->db->begin_transaction(MYSQLI_TRANS_START_READ_WRITE);
        $query = $this->db->query($new_deal_sql);
        $query = $this->db->query($get_deal_id_sql);
        $this->db->commit();
        return $this->query_handler($query);
    }

    public function delete_deal($deal_id)
    {
        $sql = "DELETE FROM deal WHERE deal_id = ".$deal_id.";";
        if ($this->db->query($sql))
        {
            return True;
        } else {
            return ['error' => $this->db->error];
        }
    }

    public function update_deal_status($deal_id, $status)
    {
        if ($this->select_deal_status($deal_id)[0]['status'] == 8) {
            return ['message'=>"Cannot update archive deal"];
        } else {
            $sql = "UPDATE deal SET status = ".$status." WHERE deal_id = ".$deal_id.";";
            if ($this->db->query($sql))
            {
                return True;
            } else {
                return ['error' => $this->db->error];
            }
        }
    }

    // здесь распоряжаться может только суперадмин - делать либо админом, либо обычным юзером
    public function update_user_type($user_id, $new_type) // 1 - user, 2 - admin, 3 - superadmin
    {
        $sql = "UPDATE user SET type = ".$new_type." WHERE user_id = ".$user_id.";";
        if ($this->db->query($sql))
        {
            return True;
        } else {
            return ['error' => $this->db->error];
        }
    }

    public function update_deal_info($deal_id, $deal_info)
    {
        $deal_info = $this->escape_string_array($deal_info);
        if ($this->select_deal_status($deal_id)[0]['status'] == 8) {
            return ['message'=>"Cannot update archive deal"];
        } else {
            $head_upd_deal_sql = "UPDATE deal SET ";
            $tail_upd_deal_sql = " WHERE deal_id = ".$deal_id.";";
            foreach (array_keys($deal_info) as $key) {
                if (strcmp($deal_info[$key], '') != 0) {
                    // echo $deal_info[$key];
                    if (is_numeric($deal_info[$key])) {
                        $head_upd_deal_sql = $head_upd_deal_sql.$key." = ".$deal_info[$key].", ";
                    } else {
                        $head_upd_deal_sql = $head_upd_deal_sql.$key." = '".$deal_info[$key]."', ";
                    }
                } else {
                    $head_upd_deal_sql = $head_upd_deal_sql.$key." = NULL, ";
                }
            }
            $head_upd_deal_sql = substr($head_upd_deal_sql, 0, strlen($head_upd_deal_sql)-2);
            $upd_deal_sql = $head_upd_deal_sql.$tail_upd_deal_sql;
            if ($this->db->query($upd_deal_sql))
            {
                return True;
            } else {
                return ['error' => $this->db->error];
            }
        }
    }

    public function update_client_info($client_id, $client_info)
    {
        $client_info = $this->escape_string_array($client_info);
        $head_upd_client_sql = "UPDATE client SET ";
        $tail_upd_client_sql = " WHERE client_id = ".$client_id.";";
        foreach (array_keys($client_info) as $key) {
            if (strcmp($client_info[$key], '') != 0) {
                $head_upd_client_sql = $head_upd_client_sql.$key." = '".$client_info[$key]."', ";
            }
            else {
                $head_upd_client_sql = $head_upd_client_sql.$key." = NULL, ";
            }
        }
        $head_upd_client_sql = substr($head_upd_client_sql, 0, strlen($head_upd_client_sql)-2);
        $upd_client_sql = $head_upd_client_sql.$tail_upd_client_sql;
        if ($this->db->query($upd_client_sql))
        {
            return True;
        } else {
            return ['error' => $this->db->error];
        }
    }

    public function update_account_info($user_id, $account_info)
    {
        $account_info = $this->escape_string_array($account_info);
        $head_account_upd_sql = "UPDATE user SET ";
        $tail_account_upd_sql = " WHERE user_id = ".$user_id.";";
        foreach (array_keys($account_info) as $key) {
            if (strcmp($account_info[$key], '') != 0) {
                $head_account_upd_sql = $head_account_upd_sql.$key." = '".$account_info[$key]."', ";
            }
            else {
                $head_account_upd_sql = $head_account_upd_sql.$key." = NULL, ";
            }
        }
        $head_account_upd_sql = substr($head_account_upd_sql, 0, strlen($head_account_upd_sql)-2);
        $upd_account_sql = $head_account_upd_sql.$tail_account_upd_sql;
        if ($this->db->query($upd_account_sql))
        {
            return True;
        } else {
            return False;
        }
    }

    public function link_another_client($deal_id, $client_id) {
        $sql = "UPDATE deal SET client_id = ".$client_id." WHERE deal_id = ".$deal_id.";";
        if ($this->db->query($sql))
        {
            return True;
        } else {
            return ['error' => $this->db->error];
        }
    }

    public function escape_string_array($array)
    {
        foreach ($array as &$element)
        {
            $element = $this->db->real_escape_string($element);
        }
        return $array;
    }

    public function has_access($deal_id, $user_id)
    {
        $sql = "SELECT user_id FROM deal WHERE deal_id = ".$deal_id.";";
        $query = $this->db->query($sql);
        if ($this->query_handler($query)[0]['user_id'] == $user_id)
        {
            return True;
        }
        else {
            return False;
        }
    }

    public function get_deal_file_info($deal_id, $fileUrls) {
        $filedata['data'] = [];
        $filedata['config'] = [];
        $deal_files = $this->select_deal_files_dir($deal_id)[0]['files'];
        if($deal_files != NULL) {
            $directory = __DIR__.'/files/'.$deal_files;
            if(is_dir($directory)) {
                $files_list = array_diff(scandir($directory), array('..', '.'));
                $filedata = [];
                if(count($files_list) > 0) {
                    foreach ($files_list as $filename) {
                        $filetype = explode('.', $filename);
                        $filedata['data'][] = $fileUrls['get'].urlencode($filename);
                        $filedata['config'][] = [
                            'caption' => $filename,
                            'size' => filesize($directory.'/'.$filename),
                            'url' => $fileUrls['delete'],
                            'downloadUrl' => $fileUrls['get'].urlencode($filename),
                            'key' => urlencode($filename)
                        ];
                    }
                }
            }
        }
        return $filedata;
    }    
}
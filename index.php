<?php

// const _user_id_str = "_rlt_user_id";
// const _user_token_str = "_rlt_token";

use \Psr\Http\Message\ServerRequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;
use \Firebase\JWT\JWT;
use \Firebase\JWT\ExpiredException;

require 'vendor/autoload.php';

// _jwt_key variable comes fron this file
require 'php/service/appenv.php';
// and static variables such as text come from here
require 'php/service/static.php';
require 'php/DataBase.php';
require 'php/service/validate-mailer.php';
require 'php/service/xlsxwriter.class.php';

$config = [
    'settings' => [
        // 'displayErrorDetails' => true,
        'determineRouteBeforeAppMiddleware' => true,

        // 'logger' => [
        //     'name' => 'slim-app',
        //     'level' => Monolog\Logger::DEBUG,
        //     'path' => __DIR__ . '/../logs/app.log',
        // ],
        'db' => [
            'host' => "localhost",
            'port' => "3306",
            'user' => "username",
            'pass' => "password",
            'database' => "dbname"
        ]
    ]
];

$app = new \Slim\App($config);

$authMW = function (Request $request, Response $response, $next) {
    $auth = FALSE;
    // if($request->hasHeader('Authorization')) {
    $cookies = $request->getCookieParams();
    if(array_key_exists('mremck', $cookies)) {
        // $jwt = explode(' ', $request->getHeader('Authorization'))[1];
        $jwt = $cookies['mremck'];
        try{
            $token = JWT::decode($jwt, base64_decode(_jwt_key), array('HS512'));
            $stored_token = $this->db->select_authorization_token($token->data->uid)[0]['token'];

            if($token->iss == gethostname() && $stored_token == $token->jti);
            {
                // good token, auth confirmed
                $auth = TRUE;
                $request = $request->withAttribute('userdata', $token->data);
            }
        } catch(Exception $e) {
            // bad token
        }
    } else {
        // no token
    }
    if($auth) {
        // continue to app
        return $next($request, $response);
    } else {
        // throw a 401, reidrect to login
        // return $response->withStatus(401)->withHeader('Location', 'index');;
        return $response->withRedirect($this->router->pathFor('login'), 307);
    }
};

$container = $app->getContainer();

// Register TWIG component on container
$container['view'] = function ($container) {
    $view = new \Slim\Views\Twig('templates', [
    'cache' => false,
    'debug' => true,
    'auto_reload' => true
    ]);
    
    // Instantiate and add Slim specific extension
    $basePath = rtrim(str_ireplace('index.php', '', $container['request']->getUri()->getBasePath()), '/');
    $view->addExtension(new Slim\Views\TwigExtension($container['router'], $basePath));
    
    return $view;
};

$container['db'] = function ($conf) {
    // DB CONFIG GOES HERE
    $db_connection = $conf['settings']['db'];
    $adapter = new DataBase($db_connection['host'], $db_connection['user'], $db_connection['pass'], $db_connection['database']);
    return $adapter;
};

// login/register frontend
$app->get('/login', function(Request $request, Response $response) {
    return $this->view->render($response, 'login.html.twig');
})->setName('login');

// login function
$app->post('/api/auth', function(Request $request, Response $response) {
    try {
        // $body = $request->getBody();
        // if(!$body) {
        // }
        // $user = json_decode($user, true);
        $user_raw = json_decode($request->getBody(), true);
        $user = array_combine(array_column($user_raw, 'name'), array_column($user_raw, 'value'));
        $user_data = $this->db->select_authorization_info($user['email'])[0];
        $response = $response->withHeader('Content-Type', 'application/json');
        if($user_data && $user_data['active'] != '0' && password_verify($user['password'], $user_data['password'])) {
            // form array structure and generate JWT
            // php 5 only! use random_bytes() for php 7
            $token_id = bin2hex(openssl_random_pseudo_bytes(32));
            $this->db->update_authorization_token($user_data['user_id'], $token_id);
                        
            // time constraints
            $issued_at = time();
            $active_after = $issued_at;
            $expires = $active_after + 60*60*12;
            $host = gethostname();
            $jwt_arr = [
                'iat'  => $issued_at,
                // json token id
                'jti'  => $token_id,
                // issuer
                // hostname to make sure the token comes fron where it's meant to
                'iss'  => gethostname(),
                'nbf'  => $active_after,
                'exp'  => $expires,
                // some user data
                'data' => [
                    'uid' => $user_data['user_id'],
                    // 'fullname' => $user_data['name'].' '.$user_data['surname'],
                    'type' => $user_data['type']
                ]
            ];
            $data = JWT::encode($jwt_arr, base64_decode(_jwt_key), 'HS512');
            if(setrawcookie("mremck", $data, $expires, '/', NULL, NULL, TRUE)) {
                $response->getBody()->write(json_encode(array("data" => ['success' => 1]), JSON_UNESCAPED_UNICODE));
            } else {
                $response->getBody()->write(json_encode(array("data" => ['error' => "Ошибка входа в систему."]), JSON_UNESCAPED_UNICODE));                        
            }
            // $response = $response->withHeader('Authorization', 'Basic '.$data);
            // $response->getBody()->write(json_encode(array("data" => ['success' => 1]), JSON_UNESCAPED_UNICODE));
        } else {
            $response->getBody()->write(json_encode(array("data" => ['error' => "Ошибка: неверное имя пользователя или пароль."]), JSON_UNESCAPED_UNICODE));
        }
    } catch(PDOException $e) {
        $response->getBody()->write($e->getMessage());
    }
    return $response;
})->setName('auth');

$app->post('/api/validate', function(Request $request, Response $response) {
    $response = $response->withHeader('Content-Type', 'application/json');
    
    $email = json_decode($request->getBody(), true)[0]['value'];
    // generate hash 
    $hash = sha1(sha1($email._reg_secret));
    $registerURL = 'http://'.$_SERVER['HTTP_HOST'].$this->router->pathFor('register-confirm', ['email' => urlencode($email), 'hash' => $hash]);

    $mailer = new ValidationMailer(FALSE, $email, $registerURL);
    $mailer->CharSet = 'UTF-8';

    if($mailer->send()) {
        return $response->write(json_encode(['success' => 'Письмо отправлено. Проверьте почтовый ящик.'], JSON_UNESCAPED_UNICODE));
    } else {
        return $response->write(json_encode(['error' => 'Ошибка. Пожалуйста, повторите позже.'], JSON_UNESCAPED_UNICODE));        
    }
    // validate
})->setName('validate-email');

$app->get('/register/confirm/{email}/{hash}', function(Request $request, Response $response, $args) {
    if(sha1(sha1(urldecode($args['email'])._reg_secret)) == $args['hash']) {
        // check for repeat users
        if(count($this->db->select_authorization_info($args['email'])) == 0) {
            // allow registration
            $render_data = [
                'valid_email' => true,
                'email' => urldecode($args['email'])
            ];
        } else {
            $render_data = [
                'valid_email' => false,
                'reason' => "Пользователь с таким адресом электронной почты уже существует.\n Перенаправление на вход на сайт...",
                'redirect' => true
            ];
        }
    } else {
        // redirect with error
        $render_data = [
            'valid_email' => false,
            'reason' => "Ошибка: неверная ссылка. Пожалуйста, повторите позже."
        ];
    }
    return $this->view->render($response, 'register.html.twig', $render_data);

    // return $this->view->render($response, 'login.html.twig');
})->setName('register-confirm');

$app->post('/api/register', function(Request $request, Response $response) {
    // save user data
    $user_info_raw = json_decode($request->getBody(), true);
    $user_info = array_combine(array_column($user_info_raw, 'name'), array_column($user_info_raw, 'value'));
    if(count($this->db->select_authorization_info($user_info['email'])) != 0) {
        return $response->write(json_encode(['error' => "Аккаунт с таким адресом уже существует."], JSON_UNESCAPED_UNICODE));
    }
    // encrypt password, remove the double
    $user_info['password'] = password_hash($user_info['password'], PASSWORD_DEFAULT);
    unset($user_info['passwordconfirm']);
    $new_user = $this->db->create_new_user($user_info);
    if(!$new_user || array_key_exists('error', $new_user)) {
        return $response->write(json_encode(['error' => "Не удалось зарегистрировать аккаунт."], JSON_UNESCAPED_UNICODE));
    } else {
        return $response->write(json_encode(['success' => "Ваш аккаунт создан! Дождитесь его активации администратором."], JSON_UNESCAPED_UNICODE));
    }
})->setName('register');

$app->get('/logout', function(Request $request, Response $response) {
    // remove cookie
    if(isset($_COOKIE['mremck'])) {
        // extract user data from cookie and delete the corresponding token
        $jwt = $_COOKIE['mremck'];
        try{
            $token = JWT::decode($jwt, base64_decode(_jwt_key), array('HS512'));
            // the token may not get decoded if it's outdated, but then it should not be possible to get to the logout page
            $this->db->delete_authorization_token($token->data->uid);
        } catch(Exception $e) {
            // bad token
        }
        unset($_COOKIE['mremck']);
        setcookie("mremck", null, 1, '/');
    }
    return $this->view->render($response, 'logout.html.twig');
})->setName('logout');

$app->group('/deals', function() use ($app) {
    $app->get('/{deal_id:[0-9]+}/[{user_id:[0-9]+}]', function (Request $request, Response $response, $args) {
        // get user data
        $user = $request->getAttribute('userdata');

        $current_deal_info = $this->db->select_deal_info($args['deal_id']);
        if(count($current_deal_info) > 0) {
            $filedata = $this->db->get_deal_file_info($args['deal_id'], [
                'get' => 'http://'.$_SERVER['HTTP_HOST'].$this->router->pathFor('get-deal-file', ['deal_id' => $args['deal_id'], 'filename' => ""]),
                'delete' => $this->router->pathFor('delete-deal-file', ['deal_id' => $args['deal_id']])
            ]);
    
            $render_data = [
                "user_type" => $user->type,
                "deal_owner_id" => ($user->type != 'user') && isset($args['user_id']) ? $args['user_id'] : NULL,
                "status_labels" => _deal_status,
                "current_deal" => array_merge($current_deal_info[0], 
                    $this->db->select_client_info_by_deal_id($args['deal_id'])[0], 
                    ['id' => $args['deal_id']]),
                "filePreviewConfig" => json_encode($filedata, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)
            ];
            if(($user->type != 'user') && isset($args['user_id'])) {
                $owner_data_raw = $this->db->select_account_info($args['user_id'])[0];
                $owner_data = [
                    'fullname' => $owner_data_raw['surname'].' '.
                    $owner_data_raw['name'].' '.
                    ($owner_data_raw['father_name'] == '' ? $owner_data_raw['father_name'].' ' : ''),
                    'email' => $owner_data_raw['email'],
                    'phone' => $owner_data_raw['phone']
                ];
                $render_data['deal_owner_data'] = $owner_data;
                $render_data['deal_data'] = [
                    "new" => $this->db->select_deals_list($args['user_id'], 'new'),
                    "current" => $this->db->select_deals_list($args['user_id'], 'current'),
                    "archive" => $this->db->select_deals_list($args['user_id'], 'archive')
                ];
            } else {
                $render_data['deal_data'] = [
                    "new" => $this->db->select_deals_list($user->uid, 'new'),
                    "current" => $this->db->select_deals_list($user->uid, 'current'),
                    "archive" => $this->db->select_deals_list($user->uid, 'archive')
                ];
            }
            return $this->view->render($response, 'index.html.twig', $render_data);
        } else {
            // no deal
            return $response->withRedirect($this->router->pathFor('home'), 307);
        }

    })->setName('get-deal');

    $app->post('/create', function (Request $request, Response $response) {
        $response = $response->withHeader('Content-Type', 'application/json');

        $user = $request->getAttribute('userdata');
        // $deal_full = json_decode($request->getBody(), true);
        $deal_raw = json_decode($request->getBody(), true);
        $deal_full = array_combine(array_column($deal_raw, 'name'), array_column($deal_raw, 'value'));
        if($user->type != 'user' && isset($deal_full['deal_owner_id'])) {
            $uid = $deal_full['deal_owner_id'];
        } else {
            $uid = $user->uid;
        }
        $creation_result = $this->db->create_new_deal($uid, [
            'address' => $deal_full['address'],
            'square_m' => $deal_full['area'],
            'rooms_number' => $deal_full['rooms_number'],
            'commentary' => $deal_full['comments']
        ], [
            'email' => $deal_full['email'],
            'name' => $deal_full['name'],
            'surname' => $deal_full['surname'],
            'father_name' => $deal_full['father-name'],
            'phone' => $deal_full['phone']
        ]);
        
        return $response->write(json_encode(['success'=>$this->router->pathFor('get-deal', ['deal_id' => $creation_result[0]['deal_id']])]));
    })->setName('deal-create');

    $app->post('/edit/{deal_id:[0-9]+}', function (Request $request, Response $response, $args) {
        $response = $response->withHeader('Content-Type', 'application/json');

        $user = $request->getAttribute('userdata');
        $deal_full = $request->getParsedBody();
        $deal_data = [
            'address' => $deal_full['address'],
            'square_m' => $deal_full['square_m'],
            'rooms_number' => $deal_full['rooms_number'],
            'date' => $deal_full['date'],
            'commission' => $deal_full['commission'],
            'commentary' => $deal_full['commentary']
        ];
        $client_data = [
            'name' => $deal_full['name'],
            'surname' => $deal_full['surname'],
            'father_name' => $deal_full['father_name'],
            'email' => $deal_full['email'],
            'phone' => $deal_full['phone']
        ];

        $deal_user_id = $this->db->select_user_id_by_deal_id($args['deal_id'])[0]['user_id'];

        if(($user->type != 'user' && isset($deal_full['deal_owner_id']) && $deal_user_id == $deal_full['deal_owner_id'])
            || ($user->type == 'user' && $deal_user_id == $user->uid)) {

            // if new client
            $client_id = $this->db->if_client_exists($client_data['email']);
            if(!$client_id) {
                // create client; link deal to new client
                $new_client = $this->db->insert_client($client_data);
                $success = $this->db->link_new_client($args['deal_id'], $new_client);
            } else {
                $current_client = $this->db->select_client_info_by_deal_id($args['deal_id'])[0];
                if($client_data['email'] == $current_client['client_email']) {
                    // update
                    $success = $this->db->update_client_info($client_id, $client_data);
                } else {
                    // insert and relink
                    $new_client = $this->db->insert_client($client_data);
                    $success = $this->db->link_new_client($args['deal_id'], $new_client);
                }
            }
        } else {
            $success = false;
        }

        if($success) {
            $resp = ['success' => "Сделка успешно изменена."];
        } else {
            $resp = ['error' => "Ошибка изменения сделки."];
        }
        return $response->write(json_encode($resp, JSON_UNESCAPED_UNICODE));
    })->setName('deal-edit');

    $app->post('/delete/{deal_id:[0-9]+}', function (Request $request, Response $response, $args) {
        $response = $response->withHeader('Content-Type', 'application/json');

        // delete all deal files
        $directory = __DIR__."/files/".$args['deal_id'];
        if(is_dir($directory)) {
            $files = array_diff(scandir($directory), array('.','..'));
            foreach ($files as $file) {
                unlink($directory.'/'.$file); 
            }
            rmdir($directory);
        }
        $user_id = $this->db->select_user_id_by_deal_id($args['deal_id'])[0]['user_id'];
        $this->db->delete_deal($args['deal_id']);
        return $response->write(json_encode(['target' => 'http://'.$_SERVER['HTTP_HOST'].$this->router->pathFor('edit-account', ['user_id' => $user_id])]));
    })->setName('deal-delete');

    $app->post('/status/{deal_id:[0-9]+}', function (Request $request, Response $response, $args) {
        $response = $response->withHeader('Content-Type', 'application/json');
        $user = $request->getAttribute('userdata');
        $status = $request->getParsedBody()['status'];
        if((int)$status == 9 || ($user->type != 'user' && ((int)$status > 0 || (int)$status < 9))) {
            $result = $this->db->update_deal_status($args['deal_id'], $status);
        } else {
            $result = FALSE;
        }
        return $response->write(json_encode(['success' => $result]));
    })->setName('deal-post-status');

    $app->get('/files/{deal_id:[0-9]+}/{filename}', function (Request $request, Response $response, $args) {
        // $response = $response->withHeader('Content-Type', 'application/json');
        $deal_files = $this->db->select_deal_files_dir($args['deal_id'])[0]['files'];
        if($deal_files != NULL) {
            $directory = __DIR__.'/files/'.$deal_files;
            $user = $request->getAttribute('userdata');
    
            $filename = $directory.'/'.urldecode($args['filename']);
            if(file_exists($filename)) {
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $response = $response->withHeader('Content-Type', finfo_file($finfo, $filename))
                    ->withHeader('Content-Disposition', 'attachment; filename="' .basename($filename) . '"')
                    ->withHeader('Expires', '0')
                    ->withHeader('Cache-Control', 'must-revalidate')
                    ->withHeader('Pragma', 'public');
                finfo_close($finfo);
    
                $filestream = new \Slim\Http\Stream(fopen($filename, 'rb'));
                return $response->withBody($filestream);
            } else {
                return $response->withRedirect($this->router->pathFor('get-deal', ['deal_id' => $args['deal_id']]), 307);
            }
        } else {
            return $response->withRedirect($this->router->pathFor('get-deal', ['deal_id' => $args['deal_id']]), 307);            
        }
    })->setName('get-deal-file');

    $app->post('/files/{deal_id:[0-9]+}', function (Request $request, Response $response, $args) {
        $response = $response->withHeader('Content-Type', 'application/json');
        $deal_files = $this->db->select_deal_files_dir($args['deal_id'])[0]['files'];
        if($deal_files == NULL) {
            $deal_files = uniqid();
            mkdir(__DIR__.'/files/'.$deal_files);
            $this->db->update_deal_info($args['deal_id'], ['files'=>$deal_files]);
        }
        $directory = __DIR__.'/files/'.$deal_files;
        $uploadedFiles = $request->getUploadedFiles();
        foreach ($uploadedFiles['input-deal-files'] as $uploadedFile) {
            if ($uploadedFile->getError() === UPLOAD_ERR_OK) {
                $filename = $uploadedFile->getClientFilename();
                if(file_exists($directory.'/'.$filename)) {
                    $file_extension = pathinfo($filename, PATHINFO_EXTENSION);
                    $filename = pathinfo($filename, PATHINFO_FILENAME).'_'.date('dmYHis').'.'.$file_extension;
                }

                $uploadedFile->moveTo($directory.'/'.$filename);
                chmod($directory.'/'.$filename, 0666);
                $response->write(json_encode('{}'));
            } else {
                $response->write(json_encode(["error" => 'Запись файла не удалась.']));                
            }
        }
    })->setName('post-deal-file');

    $app->post('/files/{deal_id:[0-9]+}/delete', function (Request $request, Response $response, $args) {
        $response = $response->withHeader('Content-Type', 'application/json');
        $deal_files = $this->db->select_deal_files_dir($args['deal_id'])[0]['files'];
        if($deal_files != NULL) {
            $filename = $request->getParsedBody()['key'];
            $directory = __DIR__.'/files/'.$deal_files;
    
            if(file_exists($directory.'/'.$filename)) {
                unlink($directory.'/'.$filename);
            }
        }        
        return $response->write('{}');
    })->setName('delete-deal-file');

})->add(function (Request $request, Response $response, $next) {
    $user = $request->getAttribute('userdata');
    $route = $request->getAttribute('route'); 
    $args = $route->getArguments();
    // deal_id should always be present, except for "new" route
    if(!array_key_exists('deal_id', $args) && $route->getName() != 'deal-create' 
    // and user should be allowed access
    || array_key_exists('deal_id', $args) && ($user->type == 'user' && !$this->db->has_access($args['deal_id'], $user->uid))) {
        return $response->withRedirect($this->router->pathFor('home'), 307);
    } else {
        return $next($request, $response);
    }
})->add($authMW);

$app->get('/statistics/[{user_id:[0-9]+}]', function (Request $request, Response $response, $args) {
    $user = $request->getAttribute('userdata');
    if(is_dir('/files/temp')) {
        $files = array_diff(scandir('/files/temp'), array('.','..'));
        foreach ($files as $file) {
            unlink('/files/temp/'.$file); 
        }
    }
    if($user->type != 'user') {
        if(isset($args['user_id'])) {
            // get user-specific stats
            $statistics = $this->db->select_user_statistics($args['user_id']);
            $filename = 'files/temp/'.date('d.m.Y').'_'.$statistics[0]['u_surname'].'_'.$statistics[0]['u_name'].'.xlsx';
        } else {
            // get all stats
            $statistics = $this->db->select_full_statistics();
            $filename = 'files/temp/'.date('d.m.Y').'_full.xlsx';
        }
        $keywords = array('Индекс', 'ФИО риелтора', 'Телефон риелтора', 'Email риелтора', 'ФИО клиента', 'Телефон клиента', 'Email клиента', 'Адрес', 'Площадь', 'Кол-во комнат', 'Срок окончания', 'Статус', 'Комментарий', 'Стоимость');
        
        $writer = new XLSXWriter();
        $writer->writeSheetRow('Sheet1', $keywords);
        foreach ($statistics as $key => $data) {
            // concatenate full name
            $data['u_surname'] = trim(implode(' ', [$data['u_surname'].' '.$data['u_name'].' '.$data['u_father_name']]));
            unset($data['u_name']); 
            unset($data['u_father_name']);
            $data['c_surname'] = trim(implode(' ', [$data['c_surname'].' '.$data['c_name'].' '.$data['c_father_name']]));
            unset($data['c_name']); 
            unset($data['c_father_name']);
            // replace cost with numeric
            $data['commission'] = is_null($data['commission']) ? 0 : $data['commission'];
            // change status code to text
            $data['status'] = _deal_status[$data['status']];
            $writer->writeSheetRow('Sheet1', $data);
        }
        $writer->writeToFile($filename);
    
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $response = $response->withHeader('Content-Type', finfo_file($finfo, $filename))
            ->withHeader('Content-Disposition', 'attachment; filename="' .basename($filename) . '"')
            ->withHeader('Expires', '0')
            ->withHeader('Cache-Control', 'must-revalidate')
            ->withHeader('Pragma', 'public');
        finfo_close($finfo);
        $filestream = new \Slim\Http\Stream(fopen($filename, 'rb'));
        return $response->withBody($filestream);
    } else {
        $response = $response->withHeader('Content-Type', 'application/json');
        return $response->write(json_encode(['error' => "Отказано в доступе."], JSON_UNESCAPED_UNICODE));
    }
})->add($authMW)->setName('statistics');

$app->get('/help', function (Request $request, Response $response) {
    $user = $request->getAttribute('userdata');
    $render_data = [
        "user_id" => $user->uid,
        "user_type" => $user->type,
        "account" => $this->db->select_account_info($user->uid)[0],
        "deal_data" => [
            "new" => $this->db->select_deals_list($user->uid, 'new'),
            "current" => $this->db->select_deals_list($user->uid, 'current'),
            "archive" => $this->db->select_deals_list($user->uid, 'archive')
        ],
        "help" => true
    ];
    return $this->view->render($response, 'index.html.twig', $render_data);
})->add($authMW)->setName('help');


$app->group('/account', function() use ($app) {
    $app->get('/', function (Request $request, Response $response) {
        $user = $request->getAttribute('userdata');
        $render_data = [
            "user_type" => $user->type,
            "account" => $this->db->select_account_info($user->uid)[0],
            "deal_data" => [
                "new" => $this->db->select_deals_list($user->uid, 'new'),
                "current" => $this->db->select_deals_list($user->uid, 'current'),
                "archive" => $this->db->select_deals_list($user->uid, 'archive')
            ]
        ];
        return $this->view->render($response, 'index.html.twig', $render_data);
    })->setName('home');

    $app->get('/{user_id:[0-9]+}', function (Request $request, Response $response, $args) {
        $user = $request->getAttribute('userdata');
        $usertype = $this->db->select_user_type($args['user_id'])[0];
        if(($user->type == 'admin' && $usertype['type'] == 'user') || $user->type == 'superadmin') {
            $owner_data_raw = $this->db->select_account_info($args['user_id'])[0];
            $owner_data = [
                'fullname' => $owner_data['surname'].' '.
                $owner_data['name'].' '.
                ($owner_data['father_name'] == '' ? $owner_data['father_name'].' ' : ''),
                'email' => $owner_data['email'],
                'phone' => $owner_data['phone']
            ];
            $render_data = [
                "deal_owner_id" => $args['user_id'],
                "deal_owner_data" => $owner_data,
                "user_type" => $user->type,
                "account" => $this->db->select_account_info($args['user_id'])[0],
                "deal_data" => [
                    "new" => $this->db->select_deals_list($args['user_id'], 'new'),
                    "current" => $this->db->select_deals_list($args['user_id'], 'current'),
                    "archive" => $this->db->select_deals_list($args['user_id'], 'archive')
                ]
            ];
            return $this->view->render($response, 'index.html.twig', $render_data);
        } else {
            return $response->withRedirect($this->router->pathFor('home'), 307);
        }
    })->setName('edit-account');

    $app->post('/{user_id:[0-9]+}', function (Request $request, Response $response, $args) {
        $response = $response->withHeader('Content-Type', 'application/json');
        $user = $request->getAttribute('userdata');
        $form_raw = json_decode($request->getBody(), true);
        $form = array_combine(array_column($form_raw, 'name'), array_column($form_raw, 'value'));
        unset($form['passwordconfirm']);

        if($form['password'] != "") {
            $form['password'] = password_hash($form['password'], PASSWORD_DEFAULT);
        } else {
            unset($form['password']);
        }

        if($user->uid == $args['user_id'] || ($user->type == 'admin' || $user->type == 'superadmin')) {
            if($this->db->update_account_info($args['user_id'], $form)) {
                $response->getBody()->write(json_encode(['success' => "Данные успешно сохранены."], JSON_UNESCAPED_UNICODE));
            } else {
                $response->getBody()->write(json_encode(['error' => 'Ошибка базы данных.'], JSON_UNESCAPED_UNICODE));
            }
        } else {
            $response->getBody()->write(json_encode(['error' => 'Ошибка прав доступа.'], JSON_UNESCAPED_UNICODE));
        }
        return $response;
    })->setName('post-account');

    $app->get('/list', function (Request $request, Response $response) {
        // only accessible to admin/superadmin roles
        $user = $request->getAttribute('userdata');
        $active_user_list = $this->db->select_user_list();
        $inactive_user_list = [];
        foreach($active_user_list as $key => $list_user) {
            if($list_user['active'] == 0) {
                $inactive_user_list[] = $list_user;
                unset($active_user_list[$key]);
            }
        }
        if($user->type != 'user') {
            $render_data = [
                "user_id" => $user->uid,
                "user_type" => $user->type,
                "accounts" => [
                    "active_accounts" => $active_user_list,
                    "inactive_accounts" => $inactive_user_list,
                ],
                "deal_data" => [
                    "new" => $this->db->select_deals_list($user->uid, 'new'),
                    "current" => $this->db->select_deals_list($user->uid, 'current'),
                    "archive" => $this->db->select_deals_list($user->uid, 'archive')
                ]
            ];
            return $this->view->render($response, 'index.html.twig', $render_data);
        } else {
            return $response->withRedirect($this->router->pathFor('home'), 307);
        }
    })->setName('account-list');
})->add($authMW);

$app->get('/', function (Request $request, Response $response) {
    return $response->withRedirect($this->router->pathFor('home'), 307);
});

$app->add(function (Request $request, Response $response, $next) {
    $hresponse = $response->
        withHeader('Cache-Control', 'no-cache, no-store, must-revalidate')->
        withHeader('Pragma', 'no-cache')->withHeader('Expires', '0');
    return $next($request, $hresponse);
});

$app->run();
?>
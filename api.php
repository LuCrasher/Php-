<?php
$dados = [
    'BSPAY' => [
        [
            "Status" => true,
            "Client ID" => 'usuarioteste_63c4ff6423765as',
            "Client Secret" => 'xxxxxxxxx',
            "Webhook URI" => "https://btmoney.site/gate/webhook.php"
        ]
    ],
    'FIVEPAY' => [
        [
            "Status" => false,
            "Client Secret" => '',
            "Webhook URI" => "https://btmoney.site/gate/webhook.php"
        ]
    ],
    'SUITPAY' => [
        [
            "Status" => false,
            "Client ID" => '',
            "Client Secret" => "",
            "Webhook URI" => "https://btmoney.site/gate/webhook.php"
        ]
    ],
    'PIXUP' => [
        [
            "Status" => false,
            "Client ID" => 'JuniorMartinsp_8910138788',
            "Client Secret" => "f3e8dd3737a4acd585545da06cf1645c5f702bc7638701c52fdd1379cc0740c2",
            "Webhook URI" => "https://investpix.shop/gate/webhook.php"
            ]
        ],
    'VENTUREPAY' => [
            [
                "Status" => false,
                "Client ID" => '',
                "Client publica" => '',
                "Client Secret" => "",
                "Webhook URI" => "https://btmoney.site/gate/webhook.php"
        ]
    ],
];

function escolherTabelaAleatoria($dados) {
    $tabelasComStatusTrue = array_filter($dados, function($tabela) {
        return isset($tabela[0]['Status']) && $tabela[0]['Status'] === true;
    });
    if (!empty($tabelasComStatusTrue)) {
        $chaves = array_keys($tabelasComStatusTrue);
        $chaveAleatoria = $chaves[array_rand($chaves)];
        return [
            'Tabela' => $chaveAleatoria,
            'Dados' => $tabelasComStatusTrue[$chaveAleatoria][0]
        ];
    } else {
        return null;
    }
}

function logToFile($logMessage) {
    $logFile = __DIR__ . '/daanrox_logs.txt'; 
    $currentTime = date('Y-m-d H:i:s');
    $logEntry = "[$currentTime] $logMessage" . PHP_EOL; 

    file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
}


session_start();
// header("Access-Control-Allow-Origin: http://localhost");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

$resultado = escolherTabelaAleatoria($dados);
date_default_timezone_set('America/Sao_Paulo');
$dueDate = date('Y-m-d H:i:s');
$user_id = $_SESSION['user_id'];
$numero_telefone = getUserPhoneNumber($user_id);

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Recebendo JSON do corpo da requisição
    $jsonRecebido = file_get_contents('php://input');
    $dadosRecebidos = json_decode($jsonRecebido, true); 
    
        if ($resultado['Tabela'] == 'BSPAY') {
            
            
            // $credentials = $resultado['Dados']['Client ID'] . ':' . $resultado['Dados']['Client Secret'];
            $credentials = "juniormartinsp_8910138788:f3e8dd3737a4acd585545da06cf1645c5f702bc7638701c52fdd1379cc0740c2";
            $base64_credentials = base64_encode($credentials);
            $autenticacao = sendpost('https://api.bspay.co/v2/oauth/token', [], [
                'Authorization: Basic '.$base64_credentials,
                'Content-Type: application/x-www-form-urlencoded'
            ]);
            $data = json_decode($autenticacao, true);
            logToFile("Data" . json_encode($data));
            $access_token = $data['access_token'] ?? null;
            if (!$access_token) {
                die("Token de acesso não obtido.");
            }
            $response = sendpost('https://api.bspay.co/v2/pix/qrcode', json_encode(value: [
                "amount" => $dadosRecebidos['valor'], 
                "postbackUrl" => $resultado['Dados']['Webhook URI'], 
                "payer" => [
                    "name" => "Solaras Investimentos LTDA",
                    "document" => "47288489906",
                    "email" => "fandangosqsfd@gmail.com"
                    ]
                ]), [
                    "Authorization: Bearer {$access_token}",
                    'Content-Type: application/json',
                    'Accept: application/json' 
                ]);
                logToFile("Response" . json_encode($response));
                $res = json_decode($response, true);
                if ( isset($res['qrcode'])) {
                    dbOperation('insert', 'pagamentos',  [
                        'user_id' => $user_id,
                        'valor' => $dadosRecebidos['valor'],
                        'cod_referencia' => $res['transactionId'],
                        'status' => 'Pendente',
                        'data' => $dueDate,
                        'Banco' => $resultado['Tabela'],
                        'numero_telefone' => $numero_telefone 
                    ]);
                    echo json_encode(value: [
                        'status' => 'success',
                        'message' => 'Solicitação de depósito enviada com sucesso.',
                        'copiarTexto' => $res['qrcode'],
                        'externalReference' => $res['transactionId'],
                    ]);
            } else {
                echo json_encode([
                    'status' => 'error',
                    'message' => 'Erro ao gerar Pix.',
                    'details' => $res,
                ]);
            }
        }
    }


    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $jsonRecebido = file_get_contents('php://input');
        $dadosRecebidos = json_decode($jsonRecebido, true); 
        
        // $payloadRecebido = json_encode($jsonRecebido, JSON_PRETTY_PRINT);
        // logToFile("Requisição acessada pelo usuário ID: $user_id. Resultado: $payloadRecebido.");
        // logToFile("Resultado:" . $resultado['Tabela']);
        
        if ($resultado['Tabela'] == 'BSPAY') {
        $apiKey = $resultado['Dados']['Client Secret'];
        $response = sendpost('https://api.bspay.co/v2/oauth/token', json_encode([
            'amount' => $dadosRecebidos['valor'],
            'client' => [
                'name' => 'Solaras Investimentos LTDA',
                'document' => '47288489906',
                'telefone' => $numero_telefone,
                'email' => 'fandangosqsfd@gmail.com',
            ],
            'api-key' => $apiKey,
            'postback' => $resultado['Dados']['Webhook URI'],
        ]), [
            'Content-Type: application/json',
            'Accept: application/json',
        ]);
    
        $res = json_decode($response, true);
    
        if (isset($res['paymentCode'])) {
            dbOperation('insert', 'pagamentos', [
                'user_id' => $user_id,
                'valor' => $dadosRecebidos['valor'],
                'cod_referencia' => $res['idTransaction'],
                'status' => 'Pendente',
                'data' => $dueDate,
                'Banco' => $resultado['Tabela'],
                'numero_telefone' => $numero_telefone,
            ]);
    
            echo json_encode([
                'status' => 'success',
                'message' => 'Solicitação de depósito enviada com sucesso.',
                'copiarTexto' => $res['paymentCode'],
                'externalReference' => $res['idTransaction'],
            ]);
        } else {
            echo json_encode([
                'status' => 'error',
                'message' => 'Erro ao gerar Pix.',
                'details' => $res,
            ]);
        }
    }

    if (json_last_error() === JSON_ERROR_NONE) {
        if ($resultado['Tabela'] == 'SUITPAY') {
            $response = sendpost('https://ws.suitpay.app/api/v1/gateway/request-qrcode', json_encode([
                'requestNumber' => "fandangosqsfd@gmail.com",
                'dueDate' => $dueDate,
                'amount' => $dadosRecebidos['valor'],
                'client' => [
                    'name' => "Solaras Investimentos LTDA",
                    'email' => "fandangosqsfd@gmail.com",
                    'document' => "47288489906",
                ],
                'callbackUrl' => $resultado['Dados']['Webhook URI'],
            ]), [
                'Content-Type: application/json',
                "ci: {$resultado['Dados']['Client ID']}",
                "cs: {$resultado['Dados']['Client Secret']}"
            ]);
            $res = json_decode($response, true);
            if ( isset($res['paymentCode'])) { 
                dbOperation('insert', 'pagamentos',  [
                    'user_id' => $user_id,
                    'valor' => $dadosRecebidos['valor'],
                    'cod_referencia' => $res['idTransaction'],
                    'status' => 'Pendente',
                    'data' => $dueDate,
                    'Banco' => $resultado['Tabela'],
                    'numero_telefone' => $numero_telefone 
                ]);
                echo json_encode([
                    'status' => 'success',
                    'message' => 'Solicitação de depósito enviada com sucesso.',
                    'copiarTexto' => $res['paymentCode'],
                    'externalReference' => $res['idTransaction'],
                ]);
            } else {
                echo json_encode(['erro' => $res]);
            }

        } elseif ($resultado['Tabela'] == 'BSPAY') {
                $token = 'xxxxxxxxxxxxxxxxxxxxxxx';
                
             
                $valorEmCentavos = $dadosRecebidos['valor'] * 100;
            
            
                $webhookResponse = sendpost('https://api.bspay.co/v2/consult-transaction', json_encode([
                    'url' => $resultado['Dados']['Webhook URI'],
                ]), [
                    "Authorization: {$token}",
                    'Content-Type: application/json',
                    'Accept: application/json'
                ]);
            
            
                $transactionResponse = sendpost('https://venturepay.com.br/api/create/transaction/', json_encode([
                    'amount' => $valorEmCentavos, 
                    'nome' => 'Apl Investimentos LTDA',
                    'cpf' => '12962752217', 
                    'email' => 'exemplo@gmail.com',
                    'number_phone' => '999999999', 
                    'area_code' => '11', 
                ]), [
                    "Authorization: {$token}",
                    'Content-Type: application/json',
                    'Accept: application/json'
                ]);
            
                $res = json_decode($transactionResponse, true);
            
            
                if (isset($res['last_transaction']['qr_code'])) {
                    dbOperation('insert', 'pagamentos', [
                        'user_id' => $user_id,
                        'valor' => $dadosRecebidos['valor'], 
                        'cod_referencia' => $res['id'],
                        'status' => 'Pendente',
                        'data' => $dueDate,
                        'Banco' => $resultado['Tabela'],
                        'numero_telefone' => $numero_telefone 
                    ]);
                    echo json_encode([
                        'status' => 'success',
                        'webhook_response' => $webhookResponse,
                        'message' => 'Solicitação de depósito enviada com sucesso.',
                        'copiarTexto' => $res['last_transaction']['qr_code'],
                        'externalReference' => $res['id'],
                    ]);
                    } else {
                     
                    echo json_encode([
                        'status' => 'error',
                        'message' => 'Falha ao criar a transação.',
                        'details' => $res 
                    ]);
                }
            }
            
                
        } else {
        echo json_encode(['erro' => 'JSON inválido']);
    }
} else {
    echo json_encode(['erro' => 'Método não permitido, use POST']);
}

function gerarCPF() {
    $n1 = rand(0, 9);
    $n2 = rand(0, 9);
    $n3 = rand(0, 9);
    $n4 = rand(0, 9);
    $n5 = rand(0, 9);
    $n6 = rand(0, 9);
    $n7 = rand(0, 9);
    $n8 = rand(0, 9);
    $n9 = rand(0, 9);

    $d1 = 10 * $n1 + 9 * $n2 + 8 * $n3 + 7 * $n4 + 6 * $n5 + 5 * $n6 + 4 * $n7 + 3 * $n8 + 2 * $n9;
    $d1 = 11 - ($d1 % 11);
    $d1 = ($d1 >= 10) ? 0 : $d1;

    $d2 = 11 * $n1 + 10 * $n2 + 9 * $n3 + 8 * $n4 + 7 * $n5 + 6 * $n6 + 5 * $n7 + 4 * $n8 + 3 * $n9 + 2 * $d1;
    $d2 = 11 - ($d2 % 11);
    $d2 = ($d2 >= 10) ? 0 : $d2;

    return "$n1$n2$n3.$n4$n5$n6.$n7$n8$n9-$d1$d2";
}


function getUserPhoneNumber($user_id) {
    $conn = getDBConnection();

    try {

        $sql = "SELECT telefone FROM usuarios WHERE id = :user_id";
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->execute();
        
   
        return $stmt->fetchColumn();
    } catch (PDOException $e) {
        die("Erro ao buscar número de telefone: " . $e->getMessage());
    }
}

function sendpost($url, $data, $headers) {
    $curl = curl_init();
    curl_setopt_array($curl, [
        CURLOPT_URL => $url,

        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 0,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,

        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_POSTFIELDS => $data,
        CURLOPT_HTTPHEADER => $headers,
    ]);
    
    $response = curl_exec($curl);
    if ($response === false) {
        throw new Exception('Erro na requisição cURL: ' . curl_error($curl));
    }
    curl_close($curl);
    
    return $response;
}

function getDBConnection() {
    $host = 'hostinger.com.br/'; // Substitua pelo seu host
    $db = 'solarasgt.shop'; // Substitua pelo nome do seu banco de dados
    $user = 'fandangosqsfd@gmail.com'; // Substitua pelo seu usuário do banco de dados
    $pass = 'Livro10%'; // Substitua pela sua senha do banco de dados

    try {
        $pdo = new PDO("mysql:host=$host;dbname=$db", $user, $pass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        return $pdo;
    } catch (PDOException $e) {
        die("Conexão falhou: " . $e->getMessage());
    }
}



if (!function_exists('sendpost')) {
    function sendpost($url, $payload, $headers) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $response = curl_exec($ch);
        curl_close($ch);

        return $response;
    }
}


function updateUserBalance($user_id, $amount) {
    $conn = getDBConnection();
    
    try {
        $sqlSelect = "SELECT saldo FROM usuarios WHERE id = :user_id";
        $stmtSelect = $conn->prepare($sqlSelect);
        $stmtSelect->bindParam(':user_id', $user_id);
        $stmtSelect->execute();
        $currentBalance = $stmtSelect->fetchColumn();

        $newBalance = $currentBalance + $amount;

        $sqlUpdate = "UPDATE usuarios SET saldo = :newBalance WHERE id = :user_id";
        $stmtUpdate = $conn->prepare($sqlUpdate);
        $stmtUpdate->bindParam(':newBalance', $newBalance);
        $stmtUpdate->bindParam(':user_id', $user_id);

        $stmtUpdate->execute();
        return ['newBalance' => $newBalance];
    } catch (PDOException $e) {
        logError($e->getMessage());
        return ['error' => $e->getMessage()];
    }
}

function dbOperation($operation, $table, $data = [], $conditions = '', $columns = '*') {

    $conn = getDBConnection();

    try {
        switch (strtolower($operation)) {
            case 'insert':
                $columns = implode(", ", array_keys($data));
                $placeholders = ":" . implode(", :", array_keys($data));
                $sql = "INSERT INTO $table ($columns) VALUES ($placeholders)";
                break;

            case 'select':
                $sql = "SELECT $columns FROM $table";
                if ($conditions) {
                    $sql .= " WHERE $conditions";
                }
                break;

            case 'update':
                $set = '';
                foreach ($data as $key => $value) {
                    $set .= "$key = :$key, ";
                }
                $set = rtrim($set, ', ');
                $sql = "UPDATE $table SET $set";
                if ($conditions) {
                    $sql .= " WHERE $conditions";
                }
                break;

            case 'delete':
                $sql = "DELETE FROM $table";
                if ($conditions) {
                    $sql .= " WHERE $conditions";
                }
                break;

            default:
                throw new Exception('Operação desconhecida');
        }

        $stmt = $conn->prepare($sql);

        if ($operation == 'insert' || $operation == 'update') {
            foreach ($data as $key => &$value) {
                $stmt->bindParam(":$key", $value);
            }
        }

        $stmt->execute();

        if ($operation == 'select') {
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        return $stmt->rowCount();
    } catch (Exception $e) {
        return ['error' => $e->getMessage()];
    }
}
?>

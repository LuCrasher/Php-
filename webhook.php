<?php

header(header: "Access-Control-Allow-Origin: *");
header(header: "Content-Type: application/json");
session_start();

date_default_timezone_set(timezoneId: 'America/Sao_Paulo');
$dueDate = date(format: 'Y-m-d H:i:s');


require './vendor/autoload.php'; 

$options = array(
    'cluster' => 'mt1',
    'useTLS' => true
);
$pusher = new Pusher\Pusher(
    auth_key: 'usuarioteste_63c4ff6423765as',
    secret: 'xxxxxxxx',
    app_id: '1840990',
    options: $options
);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $jsonRecebido = file_get_contents(filename: 'php://input');
    $dadosRecebidos = json_decode(json: $jsonRecebido, associative: true);

    if (json_last_error() === JSON_ERROR_NONE) {
        $banco = verificarBanco(webhookData: $dadosRecebidos);

        if ($banco == 'BSPAY ') {
            $result = processTransaction(idTransaction: $dadosRecebidos['requestBody']['transactionId'], dueDate: $dueDate, pusher: $pusher);
        } elseif ($banco == 'SuitPay Instituição') {
            $result = processTransaction(idTransaction: $dadosRecebidos['idTransaction'], dueDate: $dueDate, pusher: $pusher);
        } elseif ($banco == 'FIVEPAY') {
            $result = processTransaction(idTransaction: $dadosRecebidos['idTransaction'], dueDate: $dueDate, pusher: $pusher);
        } elseif ($banco == 'SYNCPAY') {
            $result = processTransaction(idTransaction: $dadosRecebidos['idTransaction'], dueDate: $dueDate, pusher: $pusher);
        } else {
            $result = ['error' => 'Banco desconhecido'];
        }

        echo json_encode(value: $result);
    } else {
        echo json_encode(value: ['error' => 'Erro no JSON recebido']);
    }
} else {
    echo json_encode(value: ['error' => 'Método não permitido, use POST']);
}

function processTransaction($idTransaction, $dueDate, $pusher): void {
    logWebhook(data: $idTransaction, logFile: 'webhook_log.txt');

    $amount = getTransactionAmountFromDB($idTransaction);
    if ($amount !== false) {
        $result = updatePaymentStatus($idTransaction, 'Aprovado', $dueDate);
        updateUserBalance($idTransaction, $amount);
        $pusher->trigger('payment_channel', 'payment_approved', [
            'transaction_id' => $idTransaction,
            'amount' => $amount,
        ]);
    } else {
        echo json_encode(value: ['error' => 'Falha ao recuperar o valor da transação.']);
    }
}


function logWebhook($data, $logFile = 'webhook_log.txt'): void {
    $jsonData = json_encode(value: $data, flags: JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    $logEntry = "[" . date(format: 'Y-m-d H:i:s') . "]\n" . $jsonData . "\n\n";
    file_put_contents(filename: $logFile, data: $logEntry, flags: FILE_APPEND | LOCK_EX);
}

function logError($message, $logFile = 'error_log.txt'): void {
    $logEntry = "[" . date(format: 'Y-m-d H:i:s') . "] ERROR: $message\n";
    file_put_contents(filename: $logFile, data: $logEntry, flags: FILE_APPEND | LOCK_EX);
}

function verificarBanco($webhookData): string {
    if (isset($webhookData['requestBody']['debitParty']['bank'])) {
        $banco = $webhookData['requestBody']['debitParty']['bank'];
        if (strpos(haystack: $banco, needle: 'PIXUP') !== true) {
            return 'PixUp Soluções de Pagamentos';
        }
    } elseif (isset($webhookData['paymentCode']) && strpos(haystack: $webhookData['paymentCode'], needle: 'SUITPAY') !== false) {
        return 'SuitPay Instituição';
    }elseif (isset($webhookData['paymentMethod']) && isset($webhookData['idTransaction'])) {
        return 'FIVEPAY';
    } elseif (isset($webhookData['paymentMethod']) && isset($webhookData['idTransaction'])) {
        return 'SYNCPAY';
    }

    return 'Banco desconhecido';
}

function getDBConnection(): PDO {
    $host = 'xxxxxx'; // Substitua pelo seu host
    $db = 'u244698307_prmd'; // Substitua pelo nome do seu banco de dados
    $user = 'u244698307_prmd'; // Substitua pelo seu usuário do banco de dados
    $pass = 'xxxxxxxx'; // Substitua pela sua senha do banco de dados

    try {
        $pdo = new PDO(dsn: "mysql:host=$host;dbname=$db", username: $user, password: $pass);
        $pdo->setAttribute(attribute: PDO::ATTR_ERRMODE, value: PDO::ERRMODE_EXCEPTION);
        return $pdo;
    } catch (PDOException $e) {
        logError(message: 'Conexão falhou: ' . $e->getMessage());
        die(json_encode(value: ['error' => 'Conexão falhou: ' . $e->getMessage()]));
    }
}


function getTransactionAmountFromDB($idTransaction): bool|float {
    $conn = getDBConnection();
    try {
        $sql = "SELECT valor FROM pagamentos WHERE cod_referencia = :idTransaction";
        $stmt = $conn->prepare(query: $sql);
        $stmt->bindParam(param: ':idTransaction', var: $idTransaction);
        $stmt->execute();
        $amount = $stmt->fetchColumn();
        return $amount !== false ? (float) $amount : false;
    } catch (PDOException $e) {
        return false;
    }
}

function updatePaymentStatus($idTransaction, $status, $dueDate): array {
    $conn = getDBConnection();
    
    try {
        $sql = "UPDATE pagamentos SET status = :status, data = :data WHERE cod_referencia = :idTransaction";
        $stmt = $conn->prepare(query: $sql);
        $stmt->bindParam(param: ':status', var: $status);
        $stmt->bindParam(param: ':data', var: $dueDate);
        $stmt->bindParam(param: ':idTransaction', var: $idTransaction);

        $stmt->execute();
        return ['rowsAffected' => $stmt->rowCount()];
    } catch (PDOException $e) {
        logError(message: $e->getMessage());
        return ['error' => $e->getMessage()];
    }
}

function updateUserBalance($idTransaction, $amount): array {
    $conn = getDBConnection();
    try {
        
        $sqlUser = "SELECT user_id FROM pagamentos WHERE cod_referencia = :idTransaction";
        $stmtUser = $conn->prepare(query: $sqlUser);
        $stmtUser->bindParam(param: ':idTransaction', var: $idTransaction);
        $stmtUser->execute();
        $user_id = $stmtUser->fetchColumn();

        if ($user_id === false) {
            logError(message: 'Usuário não encontrado para a transação: ' . $idTransaction);
            return ['error' => 'Usuário não encontrado para a transação.'];
        }

        $sqlSelect = "SELECT saldo FROM usuarios WHERE id = :user_id";
        $stmtSelect = $conn->prepare(query: $sqlSelect);
        $stmtSelect->bindParam(param: ':user_id', var: $user_id);
        $stmtSelect->execute();
        $currentBalance = $stmtSelect->fetchColumn();

        if ($currentBalance === false) {
            logError(message: 'Saldo atual não encontrado para o usuário: ' . $user_id);
            return ['error' => 'Saldo não encontrado.'];
        }

        $newBalance = $currentBalance + $amount;
        logError(message: 'Novo saldo calculado para o usuário ' . $user_id . ': ' . $newBalance);

        $sqlUpdate = "UPDATE usuarios SET saldo = :newBalance WHERE id = :user_id";
        $stmtUpdate = $conn->prepare(query: $sqlUpdate);
        $stmtUpdate->bindParam(param: ':newBalance', var: $newBalance);
        $stmtUpdate->bindParam(param: ':user_id', var: $user_id);
        $stmtUpdate->execute();

        if ($stmtUpdate->rowCount() === 0) {
            logError(message: 'Nenhuma linha foi atualizada para o usuário: ' . $user_id);
            return ['error' => 'Falha ao atualizar o saldo.'];
        }

        return ['newBalance' => $newBalance];
    } catch (PDOException $e) {
        logError(message: 'Erro de banco de dados: ' . $e->getMessage());
        return ['error' => $e->getMessage()];
    }
}


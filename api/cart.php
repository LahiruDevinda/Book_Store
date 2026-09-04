require_once __DIR__ . '/../config/db.php';
startSecureSession();

$pdo = getDBConnection();
$userId = $_SESSION['user']['userid'] ?? null;

$rawInput = file_get_contents('php://input');
$input = json_decode($rawInput, true) ?: $_POST;
$action = $input['action'] ?? ($_GET['action'] ?? 'get');

function getUserCartId(PDO $pdo, int $userId): int {
    $stmt = $pdo->prepare("SELECT cartid FROM Cart WHERE userid = ? LIMIT 1");
    $stmt->execute([$userId]);
    $cart = $stmt->fetch();
    if ($cart) {
        return (int)$cart['cartid'];
    }
    $stmtIns = $pdo->prepare("INSERT INTO Cart (userid) VALUES (?)");
    $stmtIns->execute([$userId]);
    return (int)$pdo->lastInsertId();
}
<?php
// Настройки подключения к БД 
$db_host = 'localhost';
$db_user = 'u82316';
$db_pass = '1579856';   
$db_name = 'u82316';

// Подключение к MySQL
try {
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Ошибка подключения к БД: " . $e->getMessage());
}

// Массивы допустимых значений
$allowed_languages = [
    'Pascal', 'C', 'C++', 'JavaScript', 'PHP', 'Python',
    'Java', 'Haskell', 'Clojure', 'Prolog', 'Scala', 'Go'
];

$allowed_genders = ['male', 'female'];

// Инициализация данных формы
$form_data = [
    'full_name' => '',
    'phone' => '',
    'email' => '',
    'birth_date' => '',
    'gender' => '',
    'biography' => '',
    'contract_accepted' => false,
    'languages' => []
];

$errors = [];
$success_message = '';

// Обработка POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $form_data['full_name'] = trim($_POST['full_name'] ?? '');
    $form_data['phone'] = trim($_POST['phone'] ?? '');
    $form_data['email'] = trim($_POST['email'] ?? '');
    $form_data['birth_date'] = trim($_POST['birth_date'] ?? '');
    $form_data['gender'] = $_POST['gender'] ?? '';
    $form_data['biography'] = trim($_POST['biography'] ?? '');
    $form_data['contract_accepted'] = isset($_POST['contract_accepted']);
    $form_data['languages'] = $_POST['languages'] ?? [];

    // Валидация (аналогично примеру, но с учётом новых стилей)
    if (empty($form_data['full_name'])) {
        $errors['full_name'] = 'ФИО обязательно для заполнения.';
    } elseif (!preg_match('/^[а-яА-Яa-zA-Z\s]+$/u', $form_data['full_name'])) {
        $errors['full_name'] = 'ФИО должно содержать только буквы и пробелы.';
    } elseif (strlen($form_data['full_name']) > 150) {
        $errors['full_name'] = 'ФИО не должно превышать 150 символов.';
    }

    if (empty($form_data['phone'])) {
        $errors['phone'] = 'Телефон обязателен.';
    } elseif (!preg_match('/^[\d\s\-\+\(\)]+$/', $form_data['phone'])) {
        $errors['phone'] = 'Телефон содержит недопустимые символы.';
    } elseif (strlen($form_data['phone']) < 6 || strlen($form_data['phone']) > 12) {
        $errors['phone'] = 'Телефон должен содержать от 6 до 12 символов.';
    }

    if (empty($form_data['email'])) {
        $errors['email'] = 'Email обязателен.';
    } elseif (!filter_var($form_data['email'], FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Некорректный формат email.';
    }

    if (empty($form_data['birth_date'])) {
        $errors['birth_date'] = 'Дата рождения обязательна.';
    } else {
        $date = DateTime::createFromFormat('Y-m-d', $form_data['birth_date']);
        if (!$date || $date->format('Y-m-d') !== $form_data['birth_date']) {
            $errors['birth_date'] = 'Некорректная дата.';
        } else {
            $today = new DateTime('today');
            if ($date > $today) {
                $errors['birth_date'] = 'Дата рождения не может быть в будущем.';
            }
        }
    }

    if (empty($form_data['gender']) || !in_array($form_data['gender'], $allowed_genders)) {
        $errors['gender'] = 'Выберите пол.';
    }

    if (empty($form_data['languages'])) {
        $errors['languages'] = 'Выберите хотя бы один язык программирования.';
    } else {
        foreach ($form_data['languages'] as $lang) {
            if (!in_array($lang, $allowed_languages)) {
                $errors['languages'] = 'Недопустимый язык.';
                break;
            }
        }
    }

    if (strlen($form_data['biography']) > 10000) {
        $errors['biography'] = 'Биография слишком длинная.';
    }

    if (!$form_data['contract_accepted']) {
        $errors['contract_accepted'] = 'Необходимо подтвердить ознакомление с контрактом.';
    }

    // Сохранение в БД при отсутствии ошибок
    if (empty($errors)) {
        try {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare("
                INSERT INTO application 
                (full_name, phone, email, birth_date, gender, biography, contract_accepted)
                VALUES (:full_name, :phone, :email, :birth_date, :gender, :biography, :contract_accepted)
            ");
            $stmt->execute([
                ':full_name' => $form_data['full_name'],
                ':phone' => $form_data['phone'],
                ':email' => $form_data['email'],
                ':birth_date' => $form_data['birth_date'],
                ':gender' => $form_data['gender'],
                ':biography' => $form_data['biography'],
                ':contract_accepted' => $form_data['contract_accepted'] ? 1 : 0
            ]);

            $application_id = $pdo->lastInsertId();

            $lang_map = [];
            $stmt = $pdo->query("SELECT id, name FROM language");
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $lang_map[$row['name']] = $row['id'];
            }

            $stmt = $pdo->prepare("INSERT INTO application_language (application_id, language_id) VALUES (?, ?)");
            foreach ($form_data['languages'] as $lang_name) {
                if (isset($lang_map[$lang_name])) {
                    $stmt->execute([$application_id, $lang_map[$lang_name]]);
                }
            }

            $pdo->commit();
            $success_message = 'Анкета успешно сохранена!';
            $form_data = ['full_name'=>'','phone'=>'','email'=>'','birth_date'=>'','gender'=>'','biography'=>'','contract_accepted'=>false,'languages'=>[]];
        } catch (Exception $e) {
            $pdo->rollBack();
            $errors['db'] = 'Ошибка базы данных: ' . $e->getMessage();
        }
    }
}

// Получаем список языков из БД
$languages_from_db = [];
$stmt = $pdo->query("SELECT name FROM language ORDER BY name");
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $languages_from_db[] = $row['name'];
}
if (empty($languages_from_db)) {
    $languages_from_db = $allowed_languages;
}

include 'f.php';
?>
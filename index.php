<?php

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

// Массив допустимых языков (для валидации)
$allowed_languages = [
    'Pascal', 'C', 'C++', 'JavaScript', 'PHP', 'Python',
    'Java', 'Haskell', 'Clojure', 'Prolog', 'Scala', 'Go'
];

// Массив допустимых значений пола
$allowed_genders = ['male', 'female'];

// Инициализация переменных для данных формы и ошибок
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

// Обработка отправки формы
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Заполняем $form_data из $_POST
    $form_data['full_name'] = trim($_POST['full_name'] ?? '');
    $form_data['phone'] = trim($_POST['phone'] ?? '');
    $form_data['email'] = trim($_POST['email'] ?? '');
    $form_data['birth_date'] = trim($_POST['birth_date'] ?? '');
    $form_data['gender'] = $_POST['gender'] ?? '';
    $form_data['biography'] = trim($_POST['biography'] ?? '');
    $form_data['contract_accepted'] = isset($_POST['contract_accepted']);
    $form_data['languages'] = $_POST['languages'] ?? [];

    // --- Валидация ---

    // ФИО: только буквы, пробелы, длина ≤150
    if (empty($form_data['full_name'])) {
        $errors['full_name'] = 'ФИО обязательно для заполнения.';
    } elseif (!preg_match('/^[а-яА-Яa-zA-Z\s]+$/u', $form_data['full_name'])) {
        $errors['full_name'] = 'ФИО должно содержать только буквы и пробелы.';
    } elseif (strlen($form_data['full_name']) > 150) {
        $errors['full_name'] = 'ФИО не должно превышать 150 символов.';
    }

    // Телефон: допустимые символы и длина от 6 до 12
    if (empty($form_data['phone'])) {
        $errors['phone'] = 'Телефон обязателен.';
    } elseif (!preg_match('/^[\d\s\-\+\(\)]+$/', $form_data['phone'])) {
        $errors['phone'] = 'Телефон содержит недопустимые символы.';
    } elseif (strlen($form_data['phone']) < 6 || strlen($form_data['phone']) > 12) {
        $errors['phone'] = 'Телефон должен содержать от 6 до 12 символов.';
    }

    // Email
    if (empty($form_data['email'])) {
        $errors['email'] = 'Email обязателен.';
    } elseif (!filter_var($form_data['email'], FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Некорректный формат email.';
    }

    // Дата рождения
    if (empty($form_data['birth_date'])) {
        $errors['birth_date'] = 'Дата рождения обязательна.';
    } else {
        $date = DateTime::createFromFormat('Y-m-d', $form_data['birth_date']);
        if (!$date || $date->format('Y-m-d') !== $form_data['birth_date']) {
            $errors['birth_date'] = 'Некорректная дата. Используйте формат ГГГГ-ММ-ДД.';
        } else {
            $today = new DateTime('today');
            if ($date > $today) {
                $errors['birth_date'] = 'Дата рождения не может быть позже сегодняшнего дня.';
            }
        }
    }

    // Пол
    if (empty($form_data['gender'])) {
        $errors['gender'] = 'Выберите пол.';
    } elseif (!in_array($form_data['gender'], $allowed_genders)) {
        $errors['gender'] = 'Недопустимое значение пола.';
    }

    // Любимые языки (хотя бы один)
    if (empty($form_data['languages'])) {
        $errors['languages'] = 'Выберите хотя бы один язык программирования.';
    } else {
        foreach ($form_data['languages'] as $lang) {
            if (!in_array($lang, $allowed_languages)) {
                $errors['languages'] = 'Выбран недопустимый язык.';
                break;
            }
        }
    }

    // Биография (необязательное поле, но можно проверить длину)
    if (strlen($form_data['biography']) > 10000) {
        $errors['biography'] = 'Биография слишком длинная (макс. 10000 символов).';
    }

    // Чекбокс согласия
    if (!$form_data['contract_accepted']) {
        $errors['contract_accepted'] = 'Необходимо подтвердить ознакомление с контрактом.';
    }

    // Если ошибок нет, сохраняем в БД
    if (empty($errors)) {
        try {
            $pdo->beginTransaction();

            // 1. Вставка в таблицу application
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

            // 2. Вставка в application_language
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
            $success_message = 'Данные успешно сохранены!';
            // Очищаем данные формы
            $form_data = array_map(function() { return ''; }, $form_data);
            $form_data['languages'] = [];
            $form_data['contract_accepted'] = false;

        } catch (Exception $e) {
            $pdo->rollBack();
            $errors['db'] = 'Ошибка при сохранении в БД: ' . $e->getMessage();
        }
    }
}

// Получаем список языков для отображения в форме (из таблицы)
$languages_from_db = [];
$stmt = $pdo->query("SELECT name FROM language ORDER BY name");
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $languages_from_db[] = $row['name'];
}
if (empty($languages_from_db)) {
    $languages_from_db = $allowed_languages;
}

// Подключаем форму
include 'f.php';
?>
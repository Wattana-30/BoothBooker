<?php

use Firebase\JWT\JWT;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\App;

return function (App $app) {

    // Register
    $app->post('/users/register', function (Request $request, Response $response) {
        $data = $request->getParsedBody();

        // ตรวจสอบ validation พื้นฐาน
        if (!isset($data['first_name'], $data['last_name'], $data['phone'], $data['email'], $data['password'])) {
            $response->getBody()->write(json_encode(['error' => 'All fields are required.']));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }

        $email = $data['email'];
        $pdo = $this->get('db');

        // ตรวจสอบว่ามี email นี้อยู่แล้วหรือไม่
        $stmt = $pdo->prepare('SELECT * FROM Users WHERE email = ?');
        $stmt->execute([$email]);

        if ($stmt->rowCount() > 0) {
            $response->getBody()->write(json_encode(['error' => 'Email already registered.']));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }

        // เข้ารหัส password
        $password = password_hash($data['password'], PASSWORD_DEFAULT);

        // เพิ่มผู้ใช้ใหม่ในฐานข้อมูล
        $stmt = $pdo->prepare('INSERT INTO Users (first_name, last_name, phone, email, password) VALUES (?, ?, ?, ?, ?)');
        $stmt->execute([$data['first_name'], $data['last_name'], $data['phone'], $email, $password]);

        // ส่งข้อความสำเร็จ
        $response->getBody()->write(json_encode(['message' => 'User registered successfully.']));
        return $response->withStatus(201)->withHeader('Content-Type', 'application/json');
    });

    // Login
    $app->post('/login', function (Request $request, Response $response) {
        $pdo = $this->get('db');
        $data = $request->getParsedBody();
        $email = $data['email'] ?? '';
        $password = $data['password'] ?? '';

        // ตรวจสอบข้อมูลการล็อกอิน
        if (empty($email) || empty($password)) {
            $response->getBody()->write(json_encode(['error' => 'Email and password are required']));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }

        // ตรวจสอบผู้ใช้ในฐานข้อมูล
        $stmt = $pdo->prepare('SELECT * FROM Users WHERE email = ?');
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            // หากล็อกอินสำเร็จ
            $payload = [
                'user_id' => $user['user_id'],
                'email' => $user['email'],
                'role' => $user['role'],
                'exp' => time() + (60 * 60) // Token ใช้งานได้ 1 ชั่วโมง
            ];

            // เข้ารหัส token ด้วย secret key
            $secretKey = 'your_secret_key'; // Replace with your actual secret key
            $token = JWT::encode($payload, $secretKey, 'HS256');

            // ส่ง response พร้อม token
            $response->getBody()->write(json_encode(['token' => $token]));
            return $response->withHeader('Content-Type', 'application/json');
        } else {
            // หากล็อกอินไม่สำเร็จ
            $response->getBody()->write(json_encode(['error' => 'Invalid email or password']));
            return $response->withStatus(401)->withHeader('Content-Type', 'application/json');
        }
    });
};




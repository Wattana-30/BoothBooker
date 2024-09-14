<?php
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\App;

return function (App $app) {

    // Middleware ตรวจสอบสิทธิ์สมาชิก
    $authMiddleware = function (Request $request, $handler) {
        $response = new \Slim\Psr7\Response();
        // Logic สำหรับการตรวจสอบ JWT และตรวจสอบสิทธิ์สมาชิก
        return $handler->handle($request);
    };

    $app->get('/booths', function (Request $request, Response $response) {
        $pdo = $this->get('db');
    
        // ดึงข้อมูลบูธทั้งหมดจากฐานข้อมูล
        $stmt = $pdo->query('SELECT booth_id, booth_name, booth_size, booth_status, booth_price, booth_image FROM Booths');
        $booths = $stmt->fetchAll();
    
        if ($booths) {
            // ถ้ามีข้อมูลบูธ เขียน response เป็น JSON
            $response->getBody()->write(json_encode($booths));
            return $response->withHeader('Content-Type', 'application/json');
        } else {
            // ถ้าไม่มีข้อมูลบูธ ส่งข้อความ error response
            $response->getBody()->write(json_encode(['error' => 'No booths available']));
            return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
        }
    });
    
    
            // ดึงข้อมูลรายละเอียดบูธ
        $app->get('/booth/{boothId}', function (Request $request, Response $response, array $args) {
            $boothId = $args['boothId']; // รับ boothId จาก URL parameter
            $pdo = $this->get('db');
        
                // ดึงข้อมูลบูธจากฐานข้อมูล
            $stmt = $pdo->prepare('SELECT booth_id, booth_name, booth_size, booth_status, booth_price, booth_image FROM Booths WHERE booth_id = ?');
            $stmt->execute([$boothId]);
            $booth = $stmt->fetch();
        
            if ($booth) {
                    // ถ้าพบข้อมูลบูธ เขียน response เป็น JSON
                $response->getBody()->write(json_encode($booth));
                return $response->withHeader('Content-Type', 'application/json');
            } else {
                // ถ้าไม่พบข้อมูลบูธ ส่ง error response
                $response->getBody()->write(json_encode(['error' => 'Booth not found']));
                return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
                }
            });
    
        
            // ตรวจสอบการจองปัจจุบันของสมาชิก
            $app->get('/bookings/{userId}', function (Request $request, Response $response, array $args) {
                $userId = $args['userId'];
                $pdo = $this->get('db');
        
                $stmt = $pdo->prepare('SELECT * FROM Bookings WHERE user_id = ?');
                $stmt->execute([$userId]);
                $bookings = $stmt->fetchAll();
        
                // เขียน response เป็น JSON
                $response->getBody()->write(json_encode($bookings));
                return $response->withHeader('Content-Type', 'application/json');
            });
    // API จองบูธ
    $app->post('/bookings', function (Request $request, Response $response) {
        $data = $request->getParsedBody();
        $memberId = $data['user_id'] ?? null;
        $boothId = $data['booth_id'] ?? null;
    
        if (!$memberId || !$boothId) {
            $response->getBody()->write(json_encode(['error' => 'User ID and Booth ID are required']));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }
    
        // เชื่อมต่อฐานข้อมูล
        $pdo = $this->get('db');
    
        // ดึงจำนวนบูธที่สมาชิกคนนี้เคยจองไว้
        $stmt = $pdo->prepare('SELECT COUNT(*) as booth_count FROM Bookings WHERE user_id = ?');
        $stmt->execute([$memberId]);
        $result = $stmt->fetch();
    
        $boothCount = (int)$result['booth_count'];
    
        // ตรวจสอบว่าสมาชิกจองไปแล้วกี่บูธ
        if ($boothCount >= 4) {
            $response->getBody()->write(json_encode(['error' => 'You have already booked 4 booths']));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }
    
        // ตรวจสอบว่าบูธที่ต้องการจองว่างอยู่หรือไม่
        $stmt = $pdo->prepare('SELECT * FROM Booths WHERE booth_id = ? AND booth_status = "ว่าง"');
        $stmt->execute([$boothId]);
        $booth = $stmt->fetch();
    
        if (!$booth) {
            $response->getBody()->write(json_encode(['error' => 'Booth not available']));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }
    
        // ดึง event_id ล่าสุดจากตาราง Events
        $stmt = $pdo->prepare('SELECT event_id FROM Events ORDER BY event_end_date DESC LIMIT 1');
        $stmt->execute();
        $latestEvent = $stmt->fetch();
        $eventId = $latestEvent['event_id'];
    
        // ทำการจองบูธพร้อม event_id
        $stmt = $pdo->prepare('INSERT INTO Bookings (user_id, booth_id, event_id, booking_date) VALUES (?, ?, ?, NOW())');
        $stmt->execute([$memberId, $boothId, $eventId]);
    
        // อัปเดตสถานะบูธว่าไม่ว่างแล้ว
        $stmt = $pdo->prepare('UPDATE Booths SET booth_status = "จองแล้ว" WHERE booth_id = ?');
        $stmt->execute([$boothId]);
    
        $response->getBody()->write(json_encode(['message' => 'Booth booked successfully', 'event_id' => $eventId]));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(201);
    });
    $app->get('/booking/{bookingId}', function (Request $request, Response $response, array $args) {
        $bookingId = $args['bookingId'];
        $pdo = $this->get('db');
    
        // ดึงข้อมูลการจองตาม bookingId
        $stmt = $pdo->prepare('
            SELECT b.booking_id, b.user_id, b.booth_id, b.booking_date, 
                   m.first_name, m.last_name, m.email, 
                   bo.booth_name, bo.booth_size, bo.booth_status
            FROM Bookings b
            JOIN Users m ON b.user_id = m.user_id
            JOIN Booths bo ON b.booth_id = bo.booth_id
            WHERE b.booking_id = ?
        ');
        $stmt->execute([$bookingId]);
        $booking = $stmt->fetch();
    
        if ($booking) {
            
            $response->getBody()->write(json_encode($booking));
            return $response->withHeader('Content-Type', 'application/json');
        } else {
            
            $response->getBody()->write(json_encode(['error' => 'Booking not found']));
            return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
        }
    });

    $app->post('/bookings/{bookingId}/payment', function (Request $request, Response $response, array $args) {
        $bookingId = $args['bookingId'];
        $data = $request->getParsedBody();
        $slipPath = $data['payment_slip'] ?? null;
    
        // เพิ่มวันที่ปัจจุบันสำหรับ payment_date
        $paymentDate = (new DateTime())->format('Y-m-d');
    
        // เชื่อมต่อฐานข้อมูล
        $pdo = $this->get('db');
    
        // ดึงข้อมูลการจองและวันที่จัดงานจากฐานข้อมูล
        $stmt = $pdo->prepare('
            SELECT b.booking_id, b.booth_id, b.booking_status, e.event_start_date 
            FROM Bookings b
            JOIN Events e ON b.event_id = e.event_id
            WHERE b.booking_id = ?
        ');
        $stmt->execute([$bookingId]);
        $booking = $stmt->fetch(PDO::FETCH_ASSOC);
    
        if (!$booking) {
            $response->getBody()->write(json_encode(['error' => 'Booking not found']));
            return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
        }
    
        // ตรวจสอบสถานะการจองว่าเป็น "ยกเลิกการจอง" หรือไม่
        if ($booking['booking_status'] === 'ยกเลิกการจอง') {
            $response->getBody()->write(json_encode(['error' => 'Booking is already canceled']));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }
    
        // คำนวณผลต่างระหว่างวันที่ปัจจุบันกับวันที่จัดงาน
        $eventStartDate = new DateTime($booking['event_start_date']);
        $currentDate = new DateTime();
        $daysDifference = $currentDate->diff($eventStartDate)->days;
        $isPastEvent = $currentDate > $eventStartDate;
    
        $pdo->beginTransaction();
    
        try {
            if ($daysDifference < 5 || $isPastEvent) {
                // อัปเดตสถานะการจองเป็น "ยกเลิกการจอง"
                $stmt = $pdo->prepare('UPDATE Bookings SET booking_status = :status WHERE booking_id = :booking_id');
                $stmt->execute([':status' => 'ยกเลิกการจอง', ':booking_id' => $bookingId]);
    
                // อัปเดตสถานะบูธเป็น "ว่าง"
                $stmt = $pdo->prepare('UPDATE Booths SET booth_status = :status WHERE booth_id = :booth_id');
                $stmt->execute([':status' => 'ว่าง', ':booth_id' => $booking['booth_id']]);
    
                $pdo->commit();
    
                $response->getBody()->write(json_encode(['message' => 'Booking has been canceled', 'status' => 'ยกเลิกการจอง']));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
    
            } else {
                // หากเหลือเวลาเท่ากับหรือมากกว่า 5 วัน ชำระเงินได้
                if (!$slipPath) {
                    $response->getBody()->write(json_encode(['error' => 'Slip path is required for payment']));
                    return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
                }
    
                // อัปเดตสถานะการจองเป็น "ชำระเงินแล้ว" และบันทึก path ของสลิป และวันที่ชำระเงิน
                $stmt = $pdo->prepare('
                    UPDATE Bookings 
                    SET booking_status = :status, payment_slip = :slip, payment_date = :date 
                    WHERE booking_id = :booking_id
                ');
                $stmt->execute([
                    ':status' => 'ชำระเงินแล้ว', 
                    ':slip' => $slipPath, 
                    ':date' => $paymentDate, 
                    ':booking_id' => $bookingId
                ]);
    
                // อัปเดตสถานะบูธเป็น "อยู่ระหว่างการตรวจสอบ"
                $stmt = $pdo->prepare('UPDATE Booths SET booth_status = :status WHERE booth_id = :booth_id');
                $stmt->execute([':status' => 'อยู่ระหว่างตรวจสอบ', ':booth_id' => $booking['booth_id']]);
    
                $pdo->commit();
    
                $response->getBody()->write(json_encode(['message' => 'Payment has been processed and booth status updated', 'status' => 'ชำระเงิน']));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
            }
    
        } catch (Exception $e) {
            $pdo->rollBack();
            $response->getBody()->write(json_encode(['error' => 'Transaction failed: ' . $e->getMessage()]));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    });
    
    

    $app->put('/bookings/cancel/{id}', function (Request $request, Response $response, array $args) {
        $bookingId = $args['id'];
    
        // Get the database connection
        $db = $this->get('db');
    
        // Begin a transaction
        $db->beginTransaction();
    
        try {
            // Update booking status to "ยกเลิกการจอง"
            $stmt = $db->prepare("UPDATE Bookings SET booking_status = 'ยกเลิกการจอง' WHERE booking_id = :id");
            $stmt->bindParam(':id', $bookingId, PDO::PARAM_INT);
            $stmt->execute();
    
            // Get the booth ID associated with this booking
            $stmt = $db->prepare("SELECT booth_id FROM Bookings WHERE booking_id = :id");
            $stmt->bindParam(':id', $bookingId, PDO::PARAM_INT);
            $stmt->execute();
            $boothId = $stmt->fetchColumn();
    
            if (!$boothId) {
                throw new Exception("Booking not found");
            }
    
            // Update booth status to "ว่าง"
            $stmt = $db->prepare("UPDATE Booths SET booth_status = 'ว่าง' WHERE booth_id = :booth_id");
            $stmt->bindParam(':booth_id', $boothId, PDO::PARAM_INT);
            $stmt->execute();
    
            // Commit the transaction
            $db->commit();
    
            // Respond with success
            $responseData = ['success' => true, 'message' => 'Booking has been canceled and booth status updated'];
    
            $response->getBody()->write(json_encode($responseData));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
    
        } catch (Exception $e) {
            // Rollback the transaction in case of error
            $db->rollBack();
            
            // Respond with error
            $responseData = ['success' => false, 'message' => $e->getMessage()];
            
            $response->getBody()->write(json_encode($responseData));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    });
    $app->put('/user/{userId}/update', function (Request $request, Response $response, array $args) {
        $userId = $args['userId'];
        $data = $request->getParsedBody();
        
        // รับค่าจาก request
        $prefix = $data['prefix'] ?? null;
        $firstname = $data['first_name'] ?? null;
        $lastname = $data['last_name'] ?? null;
        $phone = $data['phone'] ?? null;
        $email = $data['email'] ?? null;
        $password = $data['password'] ?? null;
    
        // เชื่อมต่อฐานข้อมูล
        $pdo = $this->get('db');
    
        // ตรวจสอบว่าผู้ใช้งานมีอยู่ในระบบหรือไม่
        $stmt = $pdo->prepare('SELECT * FROM Users WHERE user_id = ?');
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
        if (!$user) {
            $response->getBody()->write(json_encode(['error' => 'User not found']));
            return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
        }
    
        // ตรวจสอบว่าอีเมลซ้ำหรือไม่
        $stmt = $pdo->prepare('SELECT * FROM Users WHERE email = ? AND user_id != ?');
        $stmt->execute([$email, $userId]);
        $emailExists = $stmt->fetch(PDO::FETCH_ASSOC);
    
        if ($emailExists) {
            $response->getBody()->write(json_encode(['error' => 'Email already exists']));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }
    
        // เริ่มการทำธุรกรรม
        $pdo->beginTransaction();
        
        try {
            // อัปเดตข้อมูลผู้ใช้
            $stmt = $pdo->prepare('
                UPDATE Users 
                SET prefix = :prefix, first_name = :first_name, last_name = :last_name, phone = :phone, email = :email, password = :password 
                WHERE user_id = :user_id
            ');
            $stmt->execute([
                ':prefix' => $prefix,
                ':first_name' => $firstname,
                ':last_name' => $lastname,
                ':phone' => $phone,
                ':email' => $email,
                ':password' => password_hash($password, PASSWORD_BCRYPT),
                ':user_id' => $userId
            ]);
    
            $pdo->commit();
    
            $response->getBody()->write(json_encode(['message' => 'User information updated successfully']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
    
        } catch (Exception $e) {
            $pdo->rollBack();
            $response->getBody()->write(json_encode(['error' => 'Transaction failed: ' . $e->getMessage()]));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    });
    
    $app->get('/user/{userId}/bookings', function (Request $request, Response $response, array $args) {
        $userId = $args['userId'];
    
        // เชื่อมต่อฐานข้อมูล
        $pdo = $this->get('db');
    
        // ตรวจสอบว่าผู้ใช้มีการจองอยู่หรือไม่
        $stmt = $pdo->prepare('
            SELECT b.booking_id, bo.booth_name, z.zone_name, bo.booth_price, b.booking_status
            FROM Bookings b
            JOIN Booths bo ON b.booth_id = bo.booth_id
            JOIN Zones z ON bo.zone_id = z.zone_id
            WHERE b.user_id = ?
        ');
        $stmt->execute([$userId]);
        $bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
        if (!$bookings) {
            $response->getBody()->write(json_encode(['message' => 'No bookings found']));
            return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
        }
    
        // ส่งข้อมูลการจองกลับในรูปแบบ JSON
        $response->getBody()->write(json_encode($bookings));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
    });
    


    
    
    
    
    
};






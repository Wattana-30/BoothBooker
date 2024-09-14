<?php
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\App;

return function (App $app) {

    // Middleware ตรวจสอบสิทธิ์ผู้ดูแลระบบ
    $adminMiddleware = function (Request $request, $handler) {
        $response = new \Slim\Psr7\Response();
        // Logic สำหรับการตรวจสอบ JWT และตรวจสอบสิทธิ์ผู้ดูแลระบบ
        return $handler->handle($request);
    };

    // Route สำหรับการจัดการผู้ใช้
    $app->get('/admin/users', function (Request $request, Response $response) {
        $pdo =$this->get('db');
        $stmt = $pdo->prepare("SELECT * FROM Users");
        $stmt->execute();
        $users = $stmt->fetchAll();

        $response->getBody()->write(json_encode($users));
        return $response->withHeader('Content-Type', 'application/json');
    })->add($adminMiddleware);


    $app->get('/booths/available', function (Request $request, Response $response, $args) {
        $pdo = $this->get('db');
        $stmt = $pdo->prepare("SELECT * FROM Booths WHERE booth_status = 'ว่าง'");
        $stmt->execute();
        $booths = $stmt->fetchAll();
        
    
        $response->getBody()->write(json_encode($booths));
    
        
        return $response->withHeader('Content-Type', 'application/json');
    });

    $app->get('/admin/events', function (Request $request, Response $response){
        $pdo = $this->get('db');
        $stmt = $pdo->prepare("SELECT * FROM Events");
        $stmt->execute();
        $events = $stmt->fetchAll();
        
        $response->getBody()->write(json_encode($events));

        return $response->withHeader('Content-Type', 'application/json');
    })->add($adminMiddleware);

    // เพิ่มข้อมูลการจัดงาน
    $app->post('/admin/events', function ($request, $response, $args) {
        $data = $request->getParsedBody();
        $db = $this->get('db'); 
        
        $stmt = $db->prepare("INSERT INTO Events (event_name, event_start_date, event_end_date) 
                              VALUES (:event_name, :event_start_date, :event_end_date)");
        $stmt->bindParam(':event_name', $data['event_name']);
        $stmt->bindParam(':event_start_date', $data['event_start_date']);
        $stmt->bindParam(':event_end_date', $data['event_end_date']);
        
        if ($stmt->execute()) {
            $responseData = ['message' => 'Event created'];
            $response->getBody()->write(json_encode($responseData));
            return $response->withHeader('Content-Type', 'application/json')
                            ->withStatus(200);
        } else {
            $responseData = ['message' => 'Failed to create event'];
            $response->getBody()->write(json_encode($responseData));
            return $response->withHeader('Content-Type', 'application/json')
                            ->withStatus(500);
        }
    })->add($adminMiddleware);
    
    // ลบข้อมูลการจัดงาน
$app->delete('/admin/events/{id}', function ($request, $response, $args) {
    $eventId = $args['id']; // รับ ID ของ event จากพารามิเตอร์ URL
    $db = $this->get('db'); 

    $stmt = $db->prepare("DELETE FROM Events WHERE event_id = :event_id");
    $stmt->bindParam(':event_id', $eventId);

    if ($stmt->execute()) {
        $responseData = ['message' => 'Event deleted'];
        $response->getBody()->write(json_encode($responseData));
        return $response->withHeader('Content-Type', 'application/json')
                        ->withStatus(200);
    } else {
        $responseData = ['message' => 'Failed to delete event'];
        $response->getBody()->write(json_encode($responseData));
        return $response->withHeader('Content-Type', 'application/json')
                        ->withStatus(500);
    }
})->add($adminMiddleware);

// แก้ไขข้อมูลการจัดงาน
$app->put('/admin/events/{id}', function ($request, $response, $args) {
    $data = $request->getParsedBody();
    $id = $args['id'];
    $db = $this->get('db'); 
    $stmt =$db->prepare("UPDATE Events SET event_name = :event_name, event_start_date = :event_start_date, event_end_date = :event_end_date 
                                WHERE event_id = :id");
    $stmt->bindParam(':event_name', $data['event_name']);
    $stmt->bindParam(':event_start_date', $data['event_start_date']);
    $stmt->bindParam(':event_end_date', $data['event_end_date']);
    $stmt->bindParam(':id', $id);
    
    if ($stmt->execute()) {
        $responseData = ['message' => 'Event updated'];
        $response->getBody()->write(json_encode($responseData));
        return $response->withHeader('Content-Type', 'application/json')
                        ->withStatus(200);
       
    } else {
        $responseData = ['message' => 'Failed to update event'];
        $response->getBody()->write(json_encode($responseData));
        return $response->withHeader('Content-Type', 'application/json')
                        ->withStatus(500);
    }
})->add($adminMiddleware);
// เพิ่มบูธใหม่
$app->post('/admin/booths', function ($request, $response, $args) {
    $data = $request->getParsedBody();
    $db = $this->get('db'); 

    // เตรียมคำสั่ง SQL สำหรับเพิ่มบูธใหม่
    $stmt = $db->prepare("INSERT INTO Booths (booth_name, booth_size, booth_status, booth_price, booth_image, zone_id) 
                          VALUES (:booth_name, :booth_size, 'ว่าง', :booth_price,:booth_image, :zone_id)");

    // ผูกค่ากับพารามิเตอร์
    $stmt->bindParam(':booth_name', $data['booth_name']);
    $stmt->bindParam(':booth_size', $data['booth_size']);
    $stmt->bindParam(':booth_price', $data['booth_price']);
    $stmt->bindParam(':booth_image', $data['booth_image']);
    $stmt->bindParam(':zone_id', $data['zone_id']);

    
    if ($stmt->execute()) {
        $responseData = ['message' => 'Booth created'];
        $response->getBody()->write(json_encode($responseData));
        return $response->withHeader('Content-Type', 'application/json')
                        ->withStatus(200);
    } else {
        $responseData = ['message' => 'Failed to create Booth'];
        $response->getBody()->write(json_encode($responseData));
        return $response->withHeader('Content-Type', 'application/json')
                        ->withStatus(500);
    }
})->add($adminMiddleware);
// แก้ไขบูธ

$app->put('/admin/booths/{id}', function ($request, $response, $args) {
    $data = $request->getParsedBody();
    $id = $args['id'];
    $db = $this->get('db');
    
    $stmt = $db->prepare("UPDATE Booths SET booth_name = :booth_name, booth_size = :booth_size, booth_price = :booth_price, booth_image = :booth_image, zone_id = :zone_id 
    WHERE booth_id = :id");
 
    $stmt->bindParam(':booth_name', $data['booth_name']);
    $stmt->bindParam(':booth_size', $data['booth_size']);
    $stmt->bindParam(':booth_price', $data['booth_price']);
    $stmt->bindParam(':booth_image', $data['booth_image']);
    $stmt->bindParam(':zone_id', $data['zone_id']);
    $stmt->bindParam(':id', $id);
    
    
    if ($stmt->execute()) {
        $responseData = ['message' => 'Booth updated'];
        $response->getBody()->write(json_encode($responseData));
        return $response->withHeader('Content-Type', 'application/json')
                        ->withStatus(200);
    } else {
        $responseData = ['message' => 'Failed to update Booth'];
        $response->getBody()->write(json_encode($responseData));
        return $response->withHeader('Content-Type', 'application/json')
                        ->withStatus(500);
    }
});


    //ลบบูธ
    $app->delete('/admin/booths/{id}', function ($request, $response, $args) {
    $boothId = $args['id'];
    $db = $this->get('db');

    $stmt = $db->prepare("DELETE FROM Booths WHERE booth_id = :booth_id");
    $stmt->bindParam(':booth_id', $boothId);
    
    if ($stmt->execute()) {
        $responseData = ['message' => 'Booth deleted'];
        $response->getBody()->write(json_encode($responseData));
        return $response->withHeader('Content-Type', 'application/json')
                        ->withStatus(200);
    } else {
        $responseData = ['message' => 'Failed to delete Booth'];
        $response->getBody()->write(json_encode($responseData));
        return $response->withHeader('Content-Type', 'application/json')
        ->withStatus(500);

    }
});
// เพิ่มโซน
$app->post('/admin/zones', function ($request, $response, $args) {
    $data = $request->getParsedBody();
    $db = $this->get('db'); 

    $stmt = $db->prepare("INSERT INTO Zones (zone_name, zone_info, total_booths, event_id) 
                          VALUES (:zone_name, :zone_info, :total_booths, :event_id)");

    $stmt->bindParam(':zone_name', $data['zone_name']);
    $stmt->bindParam(':zone_info', $data['zone_info']);
    $stmt->bindParam(':total_booths', $data['total_booths']);
    $stmt->bindParam(':event_id', $data['event_id']);

    if ($stmt->execute()) {
        $responseData = ['message' => 'Zone created'];
        $response->getBody()->write(json_encode($responseData));
        return $response->withHeader('Content-Type', 'application/json')
                        ->withStatus(200);
    } else {
        $responseData = ['message' => 'Failed to create Zone'];
        $response->getBody()->write(json_encode($responseData));
        return $response->withHeader('Content-Type', 'application/json')
                        ->withStatus(500);
    }
});
//แก้ไขโซน

// แก้ไขโซน
$app->put('/admin/zones/{id}', function ($request, $response, $args) {
    $data = $request->getParsedBody();
    $id = $args['id']; // รับ ID ของโซนจากพารามิเตอร์ URL
    $db = $this->get('db');
    
    $stmt = $db->prepare("UPDATE Zones SET zone_name = :zone_name, zone_info = :zone_info, total_booths = :total_booths, event_id = :event_id 
                          WHERE zone_id = :id");

    $stmt->bindParam(':zone_name', $data['zone_name']);
    $stmt->bindParam(':zone_info', $data['zone_info']);
    $stmt->bindParam(':total_booths', $data['total_booths']);
    $stmt->bindParam(':event_id', $data['event_id']);
    $stmt->bindParam(':id', $id); // ใช้ :id สำหรับ zone_id

    if ($stmt->execute()) {
        $responseData = ['message' => 'Zone updated'];
        $response->getBody()->write(json_encode($responseData));
        return $response->withHeader('Content-Type', 'application/json')
                        ->withStatus(200);
    } else {
        $responseData = ['message' => 'Failed to update Zone'];
        $response->getBody()->write(json_encode($responseData));
        return $response->withHeader('Content-Type', 'application/json')
                        ->withStatus(500);
    }
});
//ลบโซน

$app->delete('/admin/zones/{id}' ,function ($request, $response, $args){
    $zoneID = $args['id'];
    $db = $this->get('db');

    $stmt = $db->prepare("DELETE FROM Zones WHERE zone_id = :zone_id");
    $stmt->bindParam(':zone_id', $zoneID);
    
    if ($stmt->execute()) {
        $responseData = ['message' => 'Zone deleted'];
        $response->getBody()->write(json_encode($responseData));
        return $response->withHeader('Content-Type', 'application/json')
                        ->withStatus(200);
    } else {
        $responseData = ['message' => 'Failed to delete Zone'];
        $response->getBody()->write(json_encode($responseData));
        return $response->withHeader('Content-Type', 'application/json')
                        ->withStatus(500);
    }
});
//แสดงโซน

$app->get('/admin/zones', function ($request, $response, $args) {
    $db = $this->get('db');

    $stmt = $db->query("SELECT * FROM Zones");
    $zones = $stmt->fetchAll();

    $responseData = ['Zones' => $zones];
    $response->getBody()->write(json_encode($responseData));
    return $response->withHeader('Content-Type', 'application/json')
                    ->withStatus(200);
});

};
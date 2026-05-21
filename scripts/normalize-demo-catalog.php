<?php

declare(strict_types=1);

$database = dirname(__DIR__) . '/sistema_de_reserva_app/data/sistema_de_reserva.sqlite';
$pdo = new PDO('sqlite:' . $database);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$pdo->beginTransaction();

try {
    $pdo->exec('UPDATE disciplinas SET activa = 0');
    $pdo->exec('UPDATE servicios SET activo = 0');

    $disciplines = array(
        array('Consultoria', 'Sesiones profesionales para diagnostico, estrategia y acompanamiento.', '#22d3ee'),
        array('Bienestar', 'Atenciones enfocadas en continuidad, cuidado y experiencia del cliente.', '#10b981'),
        array('Formacion', 'Bloques para clases, talleres y mentorias individuales o grupales.', '#8b5cf6'),
    );

    $disciplineIds = array();
    foreach ($disciplines as $discipline) {
        $id = fetch_id($pdo, 'SELECT id_disciplina FROM disciplinas WHERE nombre_disciplina = :name LIMIT 1', $discipline[0]);
        if ($id === null) {
            $pdo->prepare(
                'INSERT INTO disciplinas (nombre_disciplina, descripcion_disciplina, color_disciplina, activa)
                 VALUES (:name, :description, :color, 1)'
            )->execute(array(
                ':name' => $discipline[0],
                ':description' => $discipline[1],
                ':color' => $discipline[2],
            ));
            $id = (int) $pdo->lastInsertId();
        } else {
            $pdo->prepare(
                'UPDATE disciplinas
                 SET descripcion_disciplina = :description, color_disciplina = :color, activa = 1
                 WHERE id_disciplina = :id'
            )->execute(array(
                ':description' => $discipline[1],
                ':color' => $discipline[2],
                ':id' => $id,
            ));
        }
        $disciplineIds[$discipline[0]] = $id;
    }

    $services = array(
        array('Diagnostico inicial', 'Primera sesion para levantar necesidades, restricciones y siguiente accion.', $disciplineIds['Consultoria'], 30000, 60, '#22d3ee'),
        array('Sesion de seguimiento', 'Bloque de continuidad para revisar avances, resolver dudas y ajustar el plan.', $disciplineIds['Bienestar'], 36000, 75, '#10b981'),
        array('Taller personalizado', 'Sesion practica individual o grupal con objetivos definidos.', $disciplineIds['Formacion'], 45000, 90, '#8b5cf6'),
    );

    $serviceIds = array();
    foreach ($services as $service) {
        $id = fetch_id($pdo, 'SELECT id_servicio FROM servicios WHERE nombre_servicio = :name LIMIT 1', $service[0]);
        if ($id === null) {
            $pdo->prepare(
                'INSERT INTO servicios
                 (id_disciplina, nombre_servicio, descripcion_servicio, precio_servicio, duracion_minutos, modalidad_servicio, img_servicio, color, textColor, activo)
                 VALUES (:discipline, :name, :description, :price, :duration, "Presencial", "2.jpg", :color, "#ffffff", 1)'
            )->execute(array(
                ':discipline' => $service[2],
                ':name' => $service[0],
                ':description' => $service[1],
                ':price' => $service[3],
                ':duration' => $service[4],
                ':color' => $service[5],
            ));
            $id = (int) $pdo->lastInsertId();
        } else {
            $pdo->prepare(
                'UPDATE servicios
                 SET id_disciplina = :discipline, descripcion_servicio = :description, precio_servicio = :price,
                     duracion_minutos = :duration, modalidad_servicio = "Presencial", img_servicio = "2.jpg",
                     color = :color, textColor = "#ffffff", activo = 1
                 WHERE id_servicio = :id'
            )->execute(array(
                ':discipline' => $service[2],
                ':description' => $service[1],
                ':price' => $service[3],
                ':duration' => $service[4],
                ':color' => $service[5],
                ':id' => $id,
            ));
        }
        $serviceIds[] = $id;
    }

    $professionalId = fetch_id($pdo, 'SELECT id_professional FROM professionals WHERE user_professional = :name LIMIT 1', 'pro1');
    if ($professionalId === null) {
        $pdo->prepare(
            'INSERT INTO professionals
             (user_professional, pass_professional, name_professional, email_professional, phone_professional, bio_professional, calendar_color, id_disciplina, activo, id_estado)
             VALUES ("pro1", :password, "Profesional Demo", "pro1@sistema.local", "+56900000001", :bio, "#22d3ee", :discipline, 1, 2)'
        )->execute(array(
            ':password' => password_hash('Profesional123', PASSWORD_DEFAULT),
            ':bio' => 'Profesional multidisciplinario habilitado para probar reservas, agenda y flujo cliente.',
            ':discipline' => $disciplineIds['Consultoria'],
        ));
        $professionalId = (int) $pdo->lastInsertId();
    } else {
        $pdo->prepare(
            'UPDATE professionals
             SET name_professional = "Profesional Demo", email_professional = "pro1@sistema.local",
                 bio_professional = :bio, calendar_color = "#22d3ee", id_disciplina = :discipline,
                 activo = 1, id_estado = 2
             WHERE id_professional = :id'
        )->execute(array(
            ':bio' => 'Profesional multidisciplinario habilitado para probar reservas, agenda y flujo cliente.',
            ':discipline' => $disciplineIds['Consultoria'],
            ':id' => $professionalId,
        ));
    }

    $pdo->prepare('DELETE FROM professional_disciplines WHERE id_professional = :id')->execute(array(':id' => $professionalId));
    $pdo->prepare('DELETE FROM professional_services WHERE id_professional = :id')->execute(array(':id' => $professionalId));

    $disciplineStmt = $pdo->prepare('INSERT OR IGNORE INTO professional_disciplines (id_professional, id_disciplina) VALUES (:professional, :discipline)');
    foreach ($disciplineIds as $disciplineId) {
        $disciplineStmt->execute(array(':professional' => $professionalId, ':discipline' => $disciplineId));
    }

    $serviceStmt = $pdo->prepare('INSERT OR IGNORE INTO professional_services (id_professional, id_servicio) VALUES (:professional, :service)');
    foreach ($serviceIds as $serviceId) {
        $serviceStmt->execute(array(':professional' => $professionalId, ':service' => $serviceId));
    }

    $pdo->commit();
    echo "Demo catalog normalized\n";
} catch (Throwable $exception) {
    $pdo->rollBack();
    fwrite(STDERR, $exception->getMessage() . PHP_EOL);
    exit(1);
}

function fetch_id(PDO $pdo, string $sql, string $name): ?int
{
    $statement = $pdo->prepare($sql);
    $statement->execute(array(':name' => $name));
    $value = $statement->fetchColumn();

    return $value === false ? null : (int) $value;
}

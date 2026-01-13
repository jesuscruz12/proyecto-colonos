<?php
// Contraseña a hashear
$password = '123456';

// Cost = 10 (igual que en tu ejemplo)
$options = [
    'cost' => 10,
];

// Genera un hash bcrypt (empieza con $2y$)
$hash = password_hash($password, PASSWORD_BCRYPT, $options);

// Muestra el hash
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Generar hash bcrypt</title>
</head>
<body>
    <h1>Hash bcrypt de "123456"</h1>
    <p><strong><?php echo htmlspecialchars($hash); ?></strong></p>
</body>
</html>

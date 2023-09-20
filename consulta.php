<?php
// Conexión a la base de datos MySQL
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "northwind";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Error de conexión a la base de datos: " . $conn->connect_error);
}

// Obtener la consulta del usuario desde el formulario
if (isset($_POST['consulta'])) {
  $consulta = $_POST['consulta'];

  // Analizar la consulta y generar la consulta SQL correspondiente
  $sql = construirConsultaSQL($consulta);

  // Ejecutar la consulta SQL
  $result = $conn->query($sql);

  // Mostrar los resultados
  if ($result !== false) {
      if ($result->num_rows > 0) {
          echo "<h2>Resultados de la consulta:</h2>";
          while ($row = $result->fetch_assoc()) {
              echo "Nombre del producto: " . $row["product_name"] . "<br>";
              echo "Cantidad por unidad: " . $row["quantity_per_unit"] . "<br>";
              echo "Categoría: " . $row["category"] . "<br><br>";
          }
      } else {
          echo "No se encontraron resultados.";
      }
  } else {
      echo "Error en la consulta: " . $conn->error;
  }
}

// Función para construir la consulta SQL
function construirConsultaSQL($consulta) {
  // Dividir la consulta en términos
  $terminos = preg_split('/\s+/', $consulta);

  // Inicializar un array para almacenar las condiciones
  $condiciones = [];

  // Inicializar un operador por defecto
  $operador = "AND";

  // Inicializar un array para almacenar los campos por omisión
  $camposPorOmision = ["product_name", "quantity_per_unit", "category"]; // Nombres de columna correctos

  foreach ($terminos as $termino) {
      // Convertir el término a minúsculas para que la búsqueda no sea sensible a mayúsculas
      $termino = strtolower($termino);

      switch ($termino) {
          case "and":
              $operador = "AND";
              break;
          case "or":
              $operador = "OR";
              break;
          case "not":
              $operador = "NOT";
              break;
          case "cadena()":
              $cadena = array_shift($terminos); // Obtener la cadena exacta
              $condiciones[] = "(" . construirCondicionesLike($cadena, $camposPorOmision) . ")";
              break;
          case "patron()":
              $patron = array_shift($terminos); // Obtener el patrón
              $condiciones[] = "(" . construirCondicionesLike($patron, $camposPorOmision) . ")";
              break;
          case "campos()":
              $nuevosCampos = array_map('trim', explode(',', array_shift($terminos))); // Obtener nuevos campos
              // Verificar que todos los nuevos campos sean de una misma tabla
              $tablaBase = null;
              foreach ($nuevosCampos as $campo) {
                  list($tabla, $columna) = explode('.', $campo);
                  if ($tablaBase === null) {
                      $tablaBase = $tabla;
                  } elseif ($tablaBase !== $tabla) {
                      die("Error: Todos los campos deben ser de una misma tabla.");
                  }
              }
              // Actualizar los campos por omisión con los nuevos campos
              $camposPorOmision = $nuevosCampos;
              break;
          default:
              // Tratar el término como una palabra clave y buscar en los campos por omisión
              if ($operador === "NOT") {
                  $condiciones[] = "NOT (" . construirCondicionesLike($termino, $camposPorOmision) . ")";
                  $operador = "AND"; // Restaurar el operador por defecto después de "NOT"
              } else {
                  $condiciones[] = "(" . construirCondicionesLike($termino, $camposPorOmision) . ")";
              }
              break;
      }
  }

  // Construir la consulta SQL solo si hay condiciones
  if (!empty($condiciones)) {
      // Unir todas las condiciones con el operador adecuado
      return "SELECT * FROM products WHERE " . implode(" $operador ", $condiciones);
  } else {
      return "SELECT * FROM products"; // Consulta sin condiciones
  }
}

// Función para construir condiciones LIKE para los campos por omisión
function construirCondicionesLike($termino, $camposPorOmision) {
    $condicionesLike = [];
    foreach ($camposPorOmision as $campo) {
        $condicionesLike[] = "$campo LIKE '%$termino%'";
    }
    return implode(" OR ", $condicionesLike);
}

$conn->close(); // Cierra la conexión a la base de datos aquí
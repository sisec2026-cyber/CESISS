
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8" />
<title>Prueba ocultar botones</title>
<style>
  #tipoCamaraContainer {
    display: none;
    border: 1px solid blue;
    padding: 10px;
    width: 200px;
  }
</style>
</head>
<body>

<label for="equipo">Equipo:</label>
<input type="text" id="equipo" placeholder="Escribe equipo..." />

<div id="tipoCamaraContainer">
  <button id="camaraIP">IP</button>
  <button id="camaraAnaloga">Analógica</button>
</div>

<script>
  const equipoInput = document.getElementById('equipo');
  const tipoCamaraContainer = document.getElementById('tipoCamaraContainer');

  equipoInput.addEventListener('input', () => {
    const valor = equipoInput.value.trim().toLowerCase();
    console.log("Valor equipo:", valor);

    if (valor.includes('camara') || valor.includes('cámara')) {
      tipoCamaraContainer.style.display = 'block';
      console.log("Mostrando botones");
    } else {
      tipoCamaraContainer.style.display = 'none';
      console.log("Ocultando botones");
    }
  });
</script>

</body>
</html>

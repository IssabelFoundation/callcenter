function validarFile(archivo)
{
    if(!archivo) {
        alert("Debe seleccionar un archivo");
        return false;
    }else {
        return confirm('¿Está seguro de subir la información escogida?');
    }
}

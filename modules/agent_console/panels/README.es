Soporte para paneles personalizados en la Consola de Agente
-----------------------------------------------------------

Para acomodar personalizaciones en la Consola de Agente, puede ser de interés
hacer aparecer paneles adicionales como cejillas con nuevos controles. El
siguiente es un modelo para estandarizar el API para estos paneles
personalizados con el objeto de minimizar los cambios a realizar en los
archivos centrales de la Consola de Agente.

Organización de archivos
------------------------

Para agregar un nuevo panel, se debe crear un directorio dentro de panels/ que
contiene los archivos que conforman el panel. Dentro del directorio se debe de
incluir un archivo index.php, el cual contiene los puntos de entrada para
implementar la funcionalidad deseada. Además se deben incluir los siguientes
directorios debajo del panel, en caso de ser necesario:

js/
Funciones javascript que deben incluirse para implementar el panel. Todo archivo
debajo de este directorio se incluirá en un tag <script>.

lang/
Traducciones de idioma para los textos a usar en el panel. El formato de archivo
de traducción es idéntico al de los archivos usados por los módulos ordinarios.

tpl/
Archivos de plantillas a usar para el panel.

Otros directorios u archivos son ignorados por el código aunque pueden ser
incluidos explícitamente.

Funciones y clases a definir
----------------------------

Para evitar colisiones entre paneles, el index.php del panel debe de definir
una clase cuyo nombre se deriva del nombre de directorio del panel, con la
primera letra puesta en mayúscula. Por ejemplo, si el directorio se llama
"ponchador", se espera una clase "Panel_Ponchador".

Dentro de la clase se pueden definir las funciones siguientes:

static function templateContent($module_name, $smarty, $local_templates_dir, $oPaloConsola, $estado)

La función templateContent, en caso de existir, debe devolver el título y el
contenido del panel, y tiene que existir para que el panel aparezca en la
consola. El título se incluye dentro de un tag <a> que hace referencia a un
ancla "#tabs-NOMBREPANEL", donde NOMBREPANEL es el nombre del directorio de los
archivos del panel. El contenido es una cadena de texto que representa el HTML
a colocar dentro del <div>, el cual tiene un atributo "id" de valor
"tabs-NOMBREPANEL". El contenido devuelto debe estar estructurado así:

array('title' => "...", 'content' => "...")

El parámetro $smarty es el objeto Smarty que se usa para las plantillas, y puede
usarse para generar el contenido HTML. El parámetro $local_templates_dir es la
ruta al directorio tpl, en una forma que se puede usar directamente en una
invocación de Smarty, como la siguiente:

$content = $smarty->fetch("$local_templates_dir/test.tpl");


static function handleJSON_ACCION($module_name, $smarty, $local_templates_dir, $oPaloConsola, $estado)

Para cada operación definida por el panel que requiera acceso al servidor, se
puede definir una función handleJSON_ACCION, donde ACCION se usa para discriminar
la operación entre las múltiples posibles. Para construir una peticion web que
invoque la acción, se deben especificar un parámetro GET o POST llamado "action"
cuyo valor sea "nombrepanel_ACCION". El valor de "nombrepanel" identifica el
panel a usar e invocará la clase Panel_Nombrepanel. El valor de ACCION se usa
para construir el nombre del método handleJSON_ACCION. A pesar de lo que sugiere
el nombre, la función puede devolver como resultado cualquier contenido requerido,
en cualquier formato generable. La función tiene la responsabilidad de llamar
a Header() con el parámetro "Content-Type" adecuado al tipo de contenido generado.
De acuerdo al estándar del framework Elastix, se debe incluir en la petición el
parámetro "rawmode=yes" para que no se envuelva el resultado en el HTML de los
menús de Elastix.


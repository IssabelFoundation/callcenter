Soporte para mecanismos personalizados de carga de contactos
------------------------------------------------------------

Como parte de la integración de Elastix CallCenter al flujo de trabajo de un
cliente, algunas veces resulta necesario alimentar los contactos para una
campaña saliente a partir de una fuente de datos distinta a un archivo CSV. Por
ejemplo, contactos que son resultado de una consulta a una base de datos externa,
o proporcionados a través de un webservice. Para acomodar estos escenarios, se
establece un modelo y un API para estandarizar la escritura de código que
inserte contactos a partir de una fuente personalizada en una campaña, sin
cambios requeridos a los archivos centrales del módulo de campañas.

Organización de archivos
------------------------

Para agregar un nuevo cargador, se debe crear un directorio dentro de uploaders/
que contiene los archivos que conforman el cargador. Dentro de este directorio se
debe incluir un archivo index.php, el cual contiene los puntos de entrada para
implementar la funcionalidad deseada. Además se deben incluir los siguientes
directorios debajo del cargador, en caso de ser necesario:

js/
Funciones javascript que deben incluirse para implementar el cargador. Estos
archivos deben ser incluidos por la plantilla Smarty principal ubicada debajo
del directorio tpl/.

lang/
Traducciones de idioma para los textos a usar en el cargador. El formato de
archivo de traducción es idéntico al de los archivos usados por los módulos
ordinarios.

tpl/
Archivos de plantillas a usar para el cargador.

Otros directorios u archivos son ignorados por el código aunque pueden ser
incluidos explícitamente.


Funciones y clases a definir
----------------------------

Para evitar colisiones entre cargadores, el index.php del cargador debe de definir
una clase cuyo nombre se deriva del nombre de directorio del cargador, con la
primera letra puesta en mayúscula. Por ejemplo, si el directorio se llama
"webservice", se espera una clase "Uploader_Webservice".

Dentro de la clase se pueden definir las funciones siguientes:

static function main($module_name, $smarty, $local_templates_dir, $pDB);

La función main() se invoca para generar el contenido del formulario con las
opciones específicas del cargador a implementar y para ejecutar la operación de
carga una vez selecionadas las opciones. Para implementar el formulario de
opciones, la clase paloForm está ya cargada y disponible. El parámetro
$local_templates_dir contiene el directorio de plantillas ya resuelto para el
cargador elegido, de forma que la plantilla plantillax.tpl puede cargarse bajo
la ruta "$local_templates_dir/plantillax.tpl". El parámetro $smarty es la
instancia de clase Smarty usada por el módulo principal. El contenido del
formulario debe finalmente devolverse como resultado de la función.

static function handleJSON_ACCION($module_name, $smarty, $local_templates_dir, $pDB);

Para cada operación adicional del cargador que requiera asistencia del servidor,
se puede definir una función handleJSON_ACCION, donde ACCION se usa para discriminar
la operación entre las múltiples posibles. Para construir una peticion web AJAX
que invoque la acción, se debe construir la acción con parámetros como los
siguientes:

menu: "campaign_out"
action: "load_contacts"
id_campaign: ID
rawmode: "yes"
uploader: nombrecargador
uploader_action: ACCION
...

El valor de "nombrecargador" identifica el cargador a usar e invocará la clase
Uploader_Nombrecargador. El valor de ACCION contenido en el parámetro
uploader_action se usa para construir el nombre del método handleJSON_ACCION. A
pesar de lo que sugiere el nombre, la función puede devolver como resultado
cualquier contenido requerido, en cualquier formato generable. La función tiene
la responsabilidad de llamar a Header() con el parámetro "Content-Type" adecuado
al tipo de contenido generado. De acuerdo al estándar del framework Elastix, se
debe incluir en la petición el parámetro "rawmode=yes" para que no se envuelva
el resultado en el HTML de los menús de Elastix. El valor de "id_campaign" es
obligatorio y se puede obtener del control con nombre "id_campaign". Con jQuery,
se puede obtener el valor con la expresión: $('input[name="id_campaign"]').val() .


Procedimiento estándar de operación al momento de cargar contactos
------------------------------------------------------------------

Al momento de ejecutarse, la función main() debe verificar si el elemento
$_POST['save'] está definido. Si lo está, se debe de iniciar el proceso de carga
con las opciones definidas en $_POST y $_FILES, según sea adecuado. El ID de
campaña para el cual cargar contactos está siempre definido en
$_REQUEST['id_campaign']. En caso de éxito en la carga, el paso más usual es
ejecutar Header("Location: ?menu=$module_name") para una redirección a la lista
de campañas conocidas.

El proceso de carga (iniciado con $_POST['save']) debe incluir las siguientes
operaciones:
1) Iniciar una transacción de base de datos usando el objeto $pDB de clase
   paloDB que se pasa como parámetro 4
2) Crear una nueva instancia de un objeto paloContactInsert. Esta clase está
   disponible al ejecutar cualquier función en el cargador. El constructor
   requiere la instancia paloDB pasada a la función main(), y el ID de campaña
   indicado en $_REQUEST['id_campaign'], en ese orden.
3) Llamar al método beforeBatchInsert de la instancia recién creada. Este método
   se encarga de realizar preparativos antes de la inserción de contactos. La
   función devuelve TRUE para éxito de la operación, o FALSE en caso de un
   error. Si ocurre un error, se debe de verificar con el texto en la variable
   de instancia errMsg.
4) Iniciar el mecanismo a partir del cual se obtienen contactos a insertar en la
   campaña. Cada contacto debe consistir de exactamente un número telefónico a
   marcar, y de cero o más atributos. Cada atributo debe definir un número de
   columna (el cual define el orden de presentación) el cual se cuenta desde 1.
   También debe definirse una etiqueta del atributo, además del valor del
   atributo. Es recomendado que el mecanismo que obtiene los contactos garantice
   que el mismo conjunto de atributos exista para todos los contactos, y que se
   definan en el mismo orden.
5) Para cada contacto obtenido se debe de invocar el método de instancia:

   paloContactInsert::insertOneContact($number, $attributes)

   En caso de éxito, este método devuelve el ID del nuevo contacto creado, el
   cual es el valor de la columna "id" de la tabla "calls" en la base de datos.
   En caso de fallo, se devuelve NULL. El parámetro $number es la cadena del
   número telefónico del contacto insertado. El parámetro $attributes es un
   arreglo cuya clave es el número de orden de contacto (contando desde 1) y
   cuyo valor es una tupla. El primer elemento es la etiqueta asignada al
   atributo y el segundo elemento es el valor del atributo.

6) Luego de terminar de insertar todos los contactos, en caso de éxito, se debe
   invocar el método de instancia afterBatchInsert, el cual se encarga de
   realizar operaciones posteriores a la inserción de comandos. La función
   devuelve TRUE para éxito de la operación, o FALSE en caso de un error. Si
   ocurre un error, se debe de verificar con el texto en la variable de
   instancia errMsg.
7) Commit de la transacción en caso de éxito, o rollback si ha fallado.


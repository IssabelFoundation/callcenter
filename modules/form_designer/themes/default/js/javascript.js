var module_name = 'form_designer';
var template_formfield = null;

$(document).ready(function() {
	if (typeof CAMPOS_FORM == 'undefined') return;
	
	// Esconder recuadro de error hasta que se deba mostrar un mensaje
	$('#message_error, .message_board').hide();
	
	// Preparar la plantilla para inserción de campos
	var tbody_formlist = $('#tbody_fieldlist');
	template_formfield = $('#tbody_fieldlist > tr').detach();
	template_formfield.addClass('formfield_row');
	
	// Insertar los campos del formulario existente (si existen)
	for (var i = 0; i < CAMPOS_FORM.length; i++) {
		var formfield = template_formfield.clone();
		formfield.find('span.formfield_order').text(CAMPOS_FORM[i].orden);
		formfield.find('input[name="formfield_id"]').val(CAMPOS_FORM[i].id);
		formfield.find('input[name="formfield_name"]').val(CAMPOS_FORM[i].etiqueta);
		formfield.find('input[name="formfield_name"]').attr('placeholder', '');
		formfield.find('td.formfield_type > select').val(CAMPOS_FORM[i].tipo);
		formfield.find('input[name="formfield_add"]').hide();
		if (CAMPOS_FORM[i].tipo == 'LIST') {
			var enumlist = formfield.find('td.formfield_enumval select.formfield_enumlist_items');
			for (var j = 0; j < CAMPOS_FORM[i].value.length; j++) {
				var opt = $('<option></option>')
					.text(CAMPOS_FORM[i].value[j])
					.val(CAMPOS_FORM[i].value[j]);
				enumlist.append(opt);
			}
			formfield.find('td.formfield_enumval span.formfield_enumval_passive').text(construir_lista_comas(enumlist));
		} else {
			formfield.find('td.formfield_enumval > span.formfield_enumval_wrap').hide();
		}
		formfield.find('td.formfield_enumval div.formfield_enumval_active').hide();
		tbody_formlist.append(formfield);
	}
	
	// Insertar el campo preparado para agregar nuevo campo
	agregar_fila_nuevo_campo();
	
	// Manejadores para interacción de modificación
	$(this).on('focus', 'tr.formfield_row > td.formfield_name > input[name="formfield_name"]', function() {
		$(this).parents('tr.formfield_row').click();
	});
	$(this).on('focus', 'tr.formfield_row > td.formfield_type > select', function() {
		$(this).parents('tr.formfield_row').click();
	});
	$(this).on('change', 'tr.formfield_row > td.formfield_type > select', function() {
		/* Los campos de tipo LIST deben mostrar los controles de items. Otros
		 * tipos de campos deben de ocultarlos. */
		var formfield = $(this).parents('tr.formfield_row');
		if ($(this).val() == 'LIST') {
			formfield.find('td.formfield_enumval > span.formfield_enumval_wrap').show();
		} else {
			formfield.find('td.formfield_enumval > span.formfield_enumval_wrap').hide();
		}
	});
	$(this).on('click', 'tr.formfield_row', function() {
		/* Al hacer clic en una fila, si el campo representado es LIST, se debe
		 * de mostrar el div de modificación de la lista. Todos los divs de
		 * todas las otras filas deben de ocultarse. */
		cerrar_enumlist_activos();
		
		$(this).find('span.formfield_enumval_passive').hide();
		$(this).find('div.formfield_enumval_active').show();
	});
	$(this).on('click', 'tr.formfield_row input[name="formfield_additem"]', function() {
		/* Al hacer clic en el botón de agregar item, se debe copiar el item
		 * a la lista de opciones, blanquear el text, y actualizar el span de
		 * opciones de vista pasiva. */
		var td_enumval = $(this).parents('td.formfield_enumval');
		var input_newitem = td_enumval.find('input[name="formfield_enumlist_newitem"]');
		var newitem = input_newitem.val().trim();
		
		if (newitem != '') {
			var enumlist = td_enumval.find('select.formfield_enumlist_items');
			var opt = $('<option></option>')
				.text(newitem)
				.val(newitem);
			enumlist.append(opt);
		}
		input_newitem.val('').focus();
		
		td_enumval.find('span.formfield_enumval_passive').text(construir_lista_comas(enumlist));
	});
	$(this).on('click', 'tr.formfield_row input[name="formfield_delitem"]', function() {
		/* Al hacer clic en el botón de quitar item, se debe remover el item que
		 * esté seleccionado de la lista de opciones, y actualizar el span de
		 * opciones de vista pasiva. */
		var td_enumval = $(this).parents('td.formfield_enumval');
		var enumlist = td_enumval.find('select.formfield_enumlist_items');
		enumlist.find('option:selected').remove();		
		td_enumval.find('span.formfield_enumval_passive').text(construir_lista_comas(enumlist));
	});
	
	$(this).on('click','tr.formfield_row input[name="formfield_add"]', function() {
		/* Al hacer clic en el botón de agregar campo, el campo tiene que tener
		 * una etiqueta no vacía, y si es LIST, una lista de opciones no vacía.
		 * Si las precondiciones fallan, se enfoca el control correspondiente.
		 * Si se cumplen, se quita la clase formfield_new, se agrega una nueva
		 * fila vacía, y se reenumeran las filas. */
		var formfield = $(this).parents('tr.formfield_row');
		if (formfield.find('input[name="formfield_name"]').val().trim() == '') {
			formfield.find('input[name="formfield_name"]').focus();
			return;
		}
		if (formfield.find('td.formfield_type > select').val() == 'LIST') {
			if (formfield.find('select.formfield_enumlist_items > option').length <= 0) {
				formfield.find('input[name="formfield_enumlist_newitem"]').focus();
				return;
			}
		}
		
		// OK, se agrega fila a listado
		formfield.removeClass('formfield_new');
		formfield.attr('title', template_formfield.attr('title'));
		formfield.find('input[name="formfield_name"]').attr('placeholder', '');
		formfield.find('input[name="formfield_add"]').hide();
		formfield.find('input[name="formfield_del"]').show();
		agregar_fila_nuevo_campo();
		renumerar_campos();
		
		$('tr.formfield_new input[name="formfield_name"]').focus();
	});
	$(this).on('click','tr.formfield_row input[name="formfield_del"]', function() {
		/* Al hacer clic en el botón de quitar campo, la fila debe de desaparecer,
		 * y todos los campos restantes deben ser renumerados, a excepción del
		 * campo nuevo, cuya numeración quedará en blanco. */
		$(this).parents('tr.formfield_row').remove();
		renumerar_campos();
	});

	// Reordenamiento de campos del formulario
	$('#tbody_fieldlist').sortable({
		items: 'tr:not(.formfield_new)',
		stop: renumerar_campos
	});
	
	// Mandar los cambios al servidor
	$('form[name="form_formulario"] input[name="apply_changes"]').on('click', function() {
		var postvars = {
			menu:			module_name,
			action:			'save',
			rawmode:		'yes',
			id:				$('form[name="form_formulario"] input[name="id_formulario"]').val(),
			nombre:			$('form[name="form_formulario"] input[name="form_nombre"]').val(),
			descripcion:	$('form[name="form_formulario"] textarea[name="form_description"]').val(),
			formfields:		$('#tbody_fieldlist > tr').filter(function(i) {
					return !($(this).hasClass('formfield_new') && $(this).find('input[name="formfield_name"]').val() == '');
				}).map(function (index) {
					return {
						id:			$(this).find('input[name="formfield_id"]').val(),
						etiqueta:	$(this).find('input[name="formfield_name"]').val(),
						tipo:		$(this).find('td.formfield_type > select').val(),
						value:		$(this).find('select.formfield_enumlist_items > option').map(function (i2) { return this.value; }).get()
					};
				}).get()
		};
		if (postvars.id == '') delete postvars.id;
		$.post('index.php', postvars, function (response) {
			if (response.action == 'error') {
				$('#mb_title').text(response.message.title);
				$('#mb_message').text(response.message.message);
				$('#message_error, .message_board').show().delay(10 * 1000).fadeOut(500);
			} else {
				window.open('?menu=' + module_name, '_parent');
			}
		});
	});
});

function construir_lista_comas(o)
{
	return o.find('option').map(function(i) { return $(this).val(); }).get().join(', ');
}

function agregar_fila_nuevo_campo()
{
	var formfield = template_formfield.clone();
	formfield.addClass('formfield_new');
	formfield.attr('title', null);
	formfield.find('span.formfield_order').text('');
	formfield.find('input[name="formfield_id"]').val('');
	formfield.find('input[name="formfield_name"]').val('');
	formfield.find('td.formfield_type > select').val('TEXT');
	formfield.find('input[name="formfield_del"]').hide();
	formfield.find('td.formfield_enumval > span.formfield_enumval_wrap').hide();
	formfield.find('td.formfield_enumval div.formfield_enumval_active').hide();
	$('#tbody_fieldlist').append(formfield);
}

function renumerar_campos()
{
	$('#tbody_fieldlist > tr.formfield_row').not('.formfield_new').each(function(i) {
		$(this).find('span.formfield_order').text(i + 1);
	});
}

function cerrar_enumlist_activos()
{
	$('tr.formfield_row').find('div.formfield_enumval_active').hide();
	$('tr.formfield_row').find('span.formfield_enumval_passive').show();
}

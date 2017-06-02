$(document).ready(function() {
    $('div.callcenter-recordings > div:first-child').click(function () {
        // Ocultar o mostrar items según la clase
        if ($(this).parent().hasClass('collapsed'))
            $(this).parent().removeClass('collapsed');
        else $(this).parent().addClass('collapsed');
    });
});
/**
 * Gestor Documental - JavaScript Principal
 * Asociación de Municipios
 */

$(document).ready(function() {
    
    // Inicializar Select2
    $('.select2').select2({
        theme: 'bootstrap-5',
        language: 'es'
    });
    
    // Inicializar DataTables
    if ($.fn.DataTable) {
        $('.datatable').DataTable({
            language: {
                url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/es-ES.json'
            },
            responsive: true,
            pageLength: 10
        });
    }
    
    // Auto-cerrar alertas después de 5 segundos
    setTimeout(function() {
        $('.alert-dismissible').fadeOut('slow', function() {
            $(this).remove();
        });
    }, 5000);
    
    // Confirmar eliminación
    $(document).on('click', '.btn-delete', function(e) {
        e.preventDefault();
        var url = $(this).attr('href');
        var text = $(this).data('confirm') || '¿Está seguro de eliminar este registro?';
        
        Swal.fire({
            title: '¿Está seguro?',
            text: text,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc3545',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Sí, eliminar',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = url;
            }
        });
    });
    
    // Formatear RUT en inputs
    $(document).on('input', '.rut-input', function() {
        var rut = $(this).val().replace(/[^0-9kK]/g, '');
        if (rut.length > 1) {
            var dv = rut.slice(-1);
            var numero = rut.slice(0, -1);
            numero = numero.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
            $(this).val(numero + '-' + dv);
        }
    });
    
    // Toggle password visibility
    $(document).on('click', '.toggle-password', function() {
        var input = $(this).closest('.input-group').find('input');
        var icon = $(this).find('i');
        
        if (input.attr('type') === 'password') {
            input.attr('type', 'text');
            icon.removeClass('bi-eye').addClass('bi-eye-slash');
        } else {
            input.attr('type', 'password');
            icon.removeClass('bi-eye-slash').addClass('bi-eye');
        }
    });
    
    // Validar formularios antes de enviar
    $('form.needs-validation').on('submit', function(e) {
        if (!this.checkValidity()) {
            e.preventDefault();
            e.stopPropagation();
        }
        $(this).addClass('was-validated');
    });
    
    // Mostrar loading en submit
    $('form.show-loading').on('submit', function() {
        showLoading();
    });
    
    console.log('Gestor Documental cargado correctamente');
});

/**
 * Mostrar overlay de carga
 */
function showLoading(message = 'Procesando...') {
    if ($('.loading-overlay').length === 0) {
        $('body').append(`
            <div class="loading-overlay">
                <div class="text-center">
                    <div class="spinner-border text-primary" role="status" style="width: 3rem; height: 3rem;">
                        <span class="visually-hidden">Cargando...</span>
                    </div>
                    <p class="mt-3 text-primary fw-bold">${message}</p>
                </div>
            </div>
        `);
    }
}

/**
 * Ocultar overlay de carga
 */
function hideLoading() {
    $('.loading-overlay').fadeOut(300, function() {
        $(this).remove();
    });
}

/**
 * Mostrar notificación toast
 */
function showToast(type, message) {
    Swal.fire({
        toast: true,
        position: 'top-end',
        icon: type,
        title: message,
        showConfirmButton: false,
        timer: 3000,
        timerProgressBar: true
    });
}

/**
 * Formatear número como moneda chilena
 */
function formatMoney(amount) {
    return '$' + new Intl.NumberFormat('es-CL').format(amount);
}

/**
 * Buscar funcionario por AJAX
 */
function buscarFuncionario(funcionarioId, callback) {
    $.ajax({
        url: APP_URL + '/ajax/funcionario.php',
        type: 'GET',
        data: { id: funcionarioId },
        dataType: 'json',
        success: function(response) {
            if (callback) callback(response);
        },
        error: function() {
            showToast('error', 'Error al buscar funcionario');
        }
    });
}

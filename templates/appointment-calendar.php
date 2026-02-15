<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
/**
 * Appointment Calendar Widget
 * 
 * Interactive calendar for selecting appointment date/time
 * 
 * @package HNG_Commerce
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

$product_id = get_the_ID();
$today = gmdate('Y-m-d');
?>

<div class="hng-appointment-calendar" data-product-id="<?php echo esc_attr($product_id); ?>">
    <div class="calendar-header">
        <button class="prev-month">&larr;</button>
        <h3 class="current-month"></h3>
        <button class="next-month">&rarr;</button>
    </div>
    
    <div class="calendar-grid"></div>
    
    <div class="time-slots" style="display:none;">
        <h4><?php esc_html_e('Horários Disponíveis', 'hng-commerce'); ?></h4>
        <div class="selected-date"></div>
        <div class="slots-container"></div>
    </div>
    
    <div class="appointment-form" style="display:none;">
        <h4><?php esc_html_e('Informações do Agendamento', 'hng-commerce'); ?></h4>
        
        <input type="hidden" name="appointment_date" id="appointment-date">
        <input type="hidden" name="appointment_time" id="appointment-time">
        
        <div class="form-group">
            <label><?php esc_html_e('Nome Completo', 'hng-commerce'); ?></label>
            <input type="text" name="customer_name" required>
        </div>
        
        <div class="form-group">
            <label><?php esc_html_e('E-mail', 'hng-commerce'); ?></label>
            <input type="email" name="customer_email" required>
        </div>
        
        <div class="form-group">
            <label><?php esc_html_e('Telefone', 'hng-commerce'); ?></label>
            <input type="tel" name="customer_phone" required>
        </div>
        
        <div class="form-group">
            <label><?php esc_html_e('Observações', 'hng-commerce'); ?></label>
            <textarea name="appointment_notes" rows="3"></textarea>
        </div>
        
        <button type="button" class="button confirm-appointment">
            <?php esc_html_e('Confirmar Agendamento', 'hng-commerce'); ?>
        </button>
    </div>
</div>

<style>
.hng-appointment-calendar {
    max-width: 600px;
    margin: 20px 0;
}

.calendar-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
}

.calendar-header button {
    background: #2196F3;
    color: white;
    border: none;
    padding: 8px 16px;
    cursor: pointer;
    border-radius: 4px;
}

.calendar-grid {
    display: grid;
    grid-template-columns: repeat(7, 1fr);
    gap: 5px;
    margin-bottom: 20px;
}

.calendar-day {
    aspect-ratio: 1;
    display: flex;
    align-items: center;
    justify-content: center;
    border: 1px solid #ddd;
    cursor: pointer;
    border-radius: 4px;
    transition: all 0.2s;
}

.calendar-day:hover:not(.disabled) {
    background-color: #e3f2fd;
}

.calendar-day.disabled {
    background-color: #f5f5f5;
    color: #ccc;
    cursor: not-allowed;
}

.calendar-day.selected {
    background-color: #2196F3;
    color: white;
}

.calendar-day.today {
    border-color: #2196F3;
    font-weight: bold;
}

.time-slots {
    margin: 20px 0;
}

.slots-container {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(100px, 1fr));
    gap: 10px;
    margin-top: 15px;
}

.time-slot {
    padding: 12px;
    background: #f5f5f5;
    border: 2px solid transparent;
    border-radius: 4px;
    text-align: center;
    cursor: pointer;
    transition: all 0.2s;
}

.time-slot:hover {
    background-color: #e3f2fd;
    border-color: #2196F3;
}

.time-slot.selected {
    background-color: #2196F3;
    color: white;
}

.appointment-form {
    margin-top: 30px;
    padding: 20px;
    background-color: #f9f9f9;
    border-radius: 4px;
}

.form-group {
    margin-bottom: 15px;
}

.form-group label {
    display: block;
    margin-bottom: 5px;
    font-weight: bold;
}

.form-group input,
.form-group textarea {
    width: 100%;
    padding: 10px;
    border: 1px solid #ddd;
    border-radius: 4px;
}

.confirm-appointment {
    width: 100%;
    padding: 15px;
    background-color: #4CAF50;
    color: white;
    border: none;
    border-radius: 4px;
    font-size: 16px;
    cursor: pointer;
}

.confirm-appointment:hover {
    background-color: #45a049;
}

.selected-date {
    font-weight: bold;
    margin-bottom: 10px;
    color: #2196F3;
}
</style>

<script>
jQuery(document).ready(function($) {
    const $calendar = $('.hng-appointment-calendar');
    const productId = $calendar.data('product-id');
    let currentMonth = new gmdate();
    let selectedDate = null;
    let selectedTime = null;
    
    function renderCalendar() {
        const year = currentMonth.getFullYear();
        const month = currentMonth.getMonth();
        
        // Update header
        const monthNames = ['Janeiro', 'Fevereiro', 'Março', 'Abril', 'Maio', 'Junho',
                           'Julho', 'Agosto', 'Setembro', 'Outubro', 'Novembro', 'Dezembro'];
        $('.current-month').text(monthNames[month] + ' ' + year);
        
        // Calculate days
        const firstDay = new gmdate(year, month, 1).getDay();
        const daysInMonth = new gmdate(year, month + 1, 0).getDate();
        const today = new gmdate();
        today.setHours(0, 0, 0, 0);
        
        const $grid = $('.calendar-grid');
        $grid.empty();
        
        // Day headers
        const dayNames = ['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb'];
        dayNames.forEach(day => {
            $grid.append(`<div class="calendar-day-header">${day}</div>`);
        });
        
        // Empty cells before first day
        for (let i = 0; i < firstDay; i++) {
            $grid.append('<div class="calendar-day disabled"></div>');
        }
        
        // Days of month
        for (let day = 1; day <= daysInMonth; day++) {
            const date = new gmdate(year, month, day);
            const dateStr = date.toISOString().split('T')[0];
            const isPast = date < today;
            const isToday = date.getTime() === today.getTime();
            
            const $day = $('<div class="calendar-day"></div>')
                .text(day)
                .data('date', dateStr);
            
            if (isPast) {
                $day.addClass('disabled');
            } else {
                if (isToday) $day.addClass('today');
                $day.on('click', function() {
                    selectDate($(this).data('date'));
                });
            }
            
            $grid.append($day);
        }
    }
    
    function selectDate(date) {
        selectedDate = date;
        $('.calendar-day').removeClass('selected');
        $(`.calendar-day[data-date="${date}"]`).addClass('selected');
        
        // Load available slots
        loadTimeSlots(date);
    }
    
    function loadTimeSlots(date) {
        $.post(hngCommerce.ajax_url, {
            action: 'hng_get_available_slots',
            product_id: productId,
            date: date,
            nonce: hngCommerce.nonce
        }, function(response) {
            if (response.success) {
                renderTimeSlots(response.data.slots, date);
            }
        });
    }
    
    function renderTimeSlots(slots, date) {
        const $container = $('.slots-container');
        $container.empty();
        
        $('.selected-date').text('Data: ' + new gmdate(date).toLocaleDateString('pt-BR'));
        $('.time-slots').show();
        
        slots.forEach(slot => {
            const $slot = $('<div class="time-slot"></div>')
                .text(slot.time)
                .data('time', slot.time)
                .on('click', function() {
                    selectTimeSlot($(this).data('time'));
                });
            
            $container.append($slot);
        });
    }
    
    function selectTimeSlot(time) {
        selectedTime = time;
        $('.time-slot').removeClass('selected');
        $(`.time-slot[data-time="${time}"]`).addClass('selected');
        
        $('#appointment-date').val(selectedDate);
        $('#appointment-time').val(selectedTime);
        
        $('.appointment-form').slideDown();
    }
    
    $('.prev-month').on('click', function() {
        currentMonth.setMonth(currentMonth.getMonth() - 1);
        renderCalendar();
    });
    
    $('.next-month').on('click', function() {
        currentMonth.setMonth(currentMonth.getMonth() + 1);
        renderCalendar();
    });
    
    $('.confirm-appointment').on('click', function() {
        const formData = {
            action: 'hng_book_appointment',
            product_id: productId,
            appointment_date: $('#appointment-date').val(),
            appointment_time: $('#appointment-time').val(),
            customer_name: $('[name="customer_name"]').val(),
            customer_email: $('[name="customer_email"]').val(),
            customer_phone: $('[name="customer_phone"]').val(),
            notes: $('[name="appointment_notes"]').val(),
            nonce: hngCommerce.nonce
        };
        
        $.post(hngCommerce.ajax_url, formData, function(response) {
            if (response.success) {
                alert('Agendamento realizado com sucesso!');
                location.reload();
            } else {
                alert(response.data.message || 'Erro ao agendar.');
            }
        });
    });
    
    // Initial render
    renderCalendar();
});
</script>

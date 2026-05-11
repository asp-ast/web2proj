// ==================== ВАШ ИСХОДНЫЙ КОД (без изменений) ====================
window.onload = function () {
    //burger
    $('.burger_menu').on('click', function(){
        $('body').toggleClass('menu_active');
    });

    // for partners
    // сликер для двух полосок картинок, причем они не должны листаться синхронно + они листаются автоматически
    let setTimer;
    const partners = document.querySelector('.autoplay').innerHTML;
    let start = false;
    function slicker() {
        let sl_w = $('.partner:eq(0)').width(),
            cols = Math.round(window.innerWidth/sl_w) + 2;
        for(let i = 0; i < Math.round(cols / 3) + 1; i++)
            $('.autoplay, .autoplay2').append(partners);
  
        console.log(cols)
        if (start) {
            $('.autoplay').slick('unslick');
            $('.autoplay2').slick('unslick');
        }
        
        $('.autoplay').slick({
            infinite: true,
            slidesToShow: cols,
            slidesToScroll: 1,
            autoplay: true,
            autoplaySpeed: 2000,
            variableWidth: true
        });
        setTimeout(function(){
          $('.autoplay2').slick({
            infinite: true,
            slidesToShow: cols,
            slidesToScroll: 1,
            autoplay: true,
            autoplaySpeed: 2000,
            variableWidth: true
          });
        },800);
  
        sl_w = $('.partner:eq(0)').width();
        $('#companies .slick:eq(0)').css('margin-left', -sl_w + "px");
        $('#companies .slick:eq(1)').css('margin-left', -(sl_w / 2) + "px");
    }
    slicker();
    start = true;
    window.addEventListener("resize", function () {
        clearTimeout(setTimer);
        setTimer = setTimeout(() => { slicker(); }, 500);
    });
  //

    // for tarifs
    // при наведении блок увеличивается
    $('.tarif_category:not(.active)').hover(function () {
      $('.tarif_category.active').removeClass('active');
    });
    $( ".tarif_category:not(.active)").on( "mouseleave", function() {
      $('.tarif_category:eq(1)').addClass('active');
    } );
  //

    // for reviews
    // для того чтобы какие-то отзывы показывались а какие-то нет + для подсчета там при пролистывании
    $(".a").css('height', $('.aa > div:eq(0)').height());
    function aa(p){
        console.log(p)
        $('.aa > div').css('opacity', '0');
        setTimeout(function(){ $('.aa > div').css('display', 'block'); }, 0);
        $('.aa > div:eq(' + p + ')').css('display', 'block');
        setTimeout(function(){ $('.aa > div:eq(' + p + ')').css('opacity', '1'); }, 0);
        
        setTimeout(function(){
            $(".a").animate({
                'height': $('.aa > div:eq(' + p + ')').height()
            }, 300, "linear");
        }, 100);
  
        $('.ednum').html((p+1).toString().padStart(2, '0'))
    }
  
    // для листалки
    p = 0, pl = $('.aa > div').length - 1;
    $('.b1').on('click', function(){
        if(p == 0) p = pl;
        else p--;
        aa(p);
    });
    $('.b2').on('click', function(){
  
        if(p == pl) p = 0;
        else p++;
        aa(p);
    });
  //

    // for FAQ
    $('#AskList > div').on('click', function(){
        $('#AskList > div').removeClass('active');
        $(this).addClass('active');
    });
};

// ==================== НОВЫЙ КОД ДЛЯ АНКЕТЫ ====================
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('mainForm');
    if (!form) return;

    // Клиентская валидация
    function validateClient() {
        const errors = {};
        const fullName = form.full_name.value.trim();
        if (!fullName) errors.full_name = 'Поле обязательно.';
        else if (!/^[A-Za-zА-Яа-яЁё\s]+$/.test(fullName)) errors.full_name = 'Допустимы только буквы и пробелы.';
        else if ([...fullName].length > 150) errors.full_name = 'Не более 150 символов.';

        const phone = form.phone.value.trim();
        if (!phone) errors.phone = 'Поле обязательно.';
        else if (!/^\+?\d{1,3}[\s\-]?\(?\d{1,4}\)?[\s\-]?\d{1,4}[\s\-]?\d{1,9}$/.test(phone))
            errors.phone = 'Некорректный формат.';

        const email = form.email.value.trim();
        if (!email) errors.email = 'Поле обязательно.';
        else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) errors.email = 'Некорректный адрес.';

        if (!form.birth_date.value) errors.birth_date = 'Поле обязательно.';
        if (!form.gender.value) errors.gender = 'Выберите пол.';
        if (!form.contract_agreed.checked) errors.contract = 'Необходимо согласие.';
        if (form.languages.selectedOptions.length === 0) errors.languages = 'Выберите язык.';

        return errors;
    }

    // Показ ошибок
    function showErrors(errors) {
        const errBox = document.getElementById('errorBox');
        if (!errBox) return;
        let html = '<div class="error-block"><ul>';
        for (const [field, msg] of Object.entries(errors)) {
            html += `<li><strong>${field}:</strong> ${msg}</li>`;
            // Подсвечиваем поле с ошибкой
            const input = form.querySelector(`[name="${field}"]`) || form.querySelector(`[name="${field}[]"]`);
            if (input) {
                const group = input.closest('.form-group');
                if (group) group.classList.add('error');
            }
        }
        html += '</ul></div>';
        errBox.innerHTML = html;
    }

    // Очистка ошибок
    function clearErrors() {
        document.querySelectorAll('.form-group.error').forEach(el => el.classList.remove('error'));
        document.querySelectorAll('.error-hint').forEach(el => el.textContent = '');
        const errBox = document.getElementById('errorBox');
        if (errBox) errBox.innerHTML = '';
    }

    // Обработка отправки
    form.addEventListener('submit', async function(e) {
        e.preventDefault();
        const clientErrors = validateClient();
        if (Object.keys(clientErrors).length > 0) {
            showErrors(clientErrors);
            return;
        }

        const formData = new FormData(form);
        const csrfToken = formData.get('csrf_token');
        const isAuthenticated = document.body.dataset.authenticated === 'true';
        const userId = document.body.dataset.userId;
        let url = 'api/applications';
        let method = 'POST';
        if (isAuthenticated) {
            url = 'api/applications/' + userId;
            method = 'PUT';
        }

        try {
            const response = await fetch(url, {
                method: method,
                headers: {
                    'X-CSRF-TOKEN': csrfToken,
                    'Accept': 'application/json'
                },
                body: formData
            });
            const result = await response.json();
            const msgBox = document.getElementById('messageBox');
            const errBox = document.getElementById('errorBox');
            if (msgBox) msgBox.innerHTML = '';
            if (errBox) errBox.innerHTML = '';

            if (!response.ok) {
                if (result.errors) {
                    showErrors(result.errors);
                } else {
                    if (errBox) errBox.innerHTML = '<div class="error-block">' + (result.error || 'Ошибка') + '</div>';
                }
                return;
            }

            if (result.success) {
                if (result.login) {
                    if (msgBox) msgBox.innerHTML = `<div class="success-block">Данные сохранены!</div>
                        <div class="message">Логин: <b>${result.login}</b>, Пароль: <b>${result.password}</b> (сохраните)<br>
                        <a href="${result.profile_url}">Перейти в профиль</a></div>`;
                } else {
                    if (msgBox) msgBox.innerHTML = '<div class="success-block">Изменения сохранены.</div>';
                }
                clearErrors();
            }
        } catch (error) {
            const errBox = document.getElementById('errorBox');
            if (errBox) errBox.innerHTML = '<div class="error-block">Сетевая ошибка.</div>';
        }
    });

    // Очистка ошибок при изменении полей (опционально)
    form.addEventListener('input', function(e) {
        if (e.target.closest('.form-group')) {
            e.target.closest('.form-group').classList.remove('error');
            const hint = e.target.closest('.form-group').querySelector('.error-hint');
            if (hint) hint.textContent = '';
        }
    });
});
class RegistrationForm {
  constructor(formId, phoneCodeId) {
    this.form = document.getElementById(formId);
    this.phoneCodeSelect = document.getElementById(phoneCodeId);
    this.phoneInput = this.form.querySelector('input[name="phone"]');
    this.selectedPhoneCode = '';
    this.userCountry = null;
    this.countriesData = null;
    this.selectedCountry = null;
    this.submissionCache = new Set();
    this.isSubmitting = false;

    // Добавляем ссылку на модальное окно
    this.modal = $('#formResponseModal');
    this.modalMessage = $('#formResponseMessage');

    this.init();
  }

  async init() {
    await this.loadUserCountry();
    await this.loadCountryCodes();
    this.initializePhoneCodes();
    this.attachEventListeners();
  }

  async loadUserCountry() {
    try {
      const response = await fetch('https://ipapi.co/json/', {
        mode: 'cors',
        headers: {
          'Accept': 'application/json'
        }
      });
      const data = await response.json();
      this.userCountry = data.country_code;
      return data.country_code;
    } catch (error) {
      console.error('Error fetching country:', error);
      this.userCountry = 'UA';
      return this.userCountry;
    }
  }

  async loadCountryCodes() {
    try {
      const response = await fetch('languages.json');
      this.countriesData = await response.json();
    } catch (error) {
      console.error('Error loading languages.json:', error);
    }
  }

  initializePhoneCodes() {
    this.phoneCodeSelect.innerHTML = '';

    const selectedFlag = document.createElement('img');
    selectedFlag.className = 'selected-flag';
    this.phoneCodeSelect.parentElement.appendChild(selectedFlag);

    const defaultOption = document.createElement('option');
    defaultOption.value = '';
    defaultOption.disabled = true;
    defaultOption.textContent = 'Выберите страну';
    this.phoneCodeSelect.appendChild(defaultOption);

    Object.entries(this.countriesData).forEach(([countryCode, data]) => {
      const option = document.createElement('option');
      option.value = countryCode;
      option.setAttribute('data-flag', data.flag);
      option.setAttribute('data-code', data.phone.code);
      option.textContent = `${countryCode}`;
      option.selected = countryCode === this.userCountry;
      this.phoneCodeSelect.appendChild(option);
    });

    if (this.userCountry && this.countriesData[this.userCountry]) {
      selectedFlag.src = this.countriesData[this.userCountry].flag;
      this.selectedPhoneCode = this.countriesData[this.userCountry].phone.code;
      this.selectedCountry = this.userCountry;
      this.updatePhoneInputMask();
    }
  }

  updatePhoneInputMask() {
    const phoneValue = this.phoneInput.value;
    const codeDigits = this.selectedPhoneCode.replace(/\D/g, '');

    if (!phoneValue) {
      this.phoneInput.value = this.selectedPhoneCode;
      return;
    }

    const cleanPhone = phoneValue.replace(/\D/g, '');

    if (!cleanPhone.startsWith(codeDigits)) {
      this.phoneInput.value = this.selectedPhoneCode + cleanPhone;
    } else {
      const phoneWithoutCode = cleanPhone.substring(codeDigits.length);
      this.phoneInput.value = this.selectedPhoneCode + phoneWithoutCode;
    }
  }

  validatePhoneNumber(phone) {
    const countryData = this.countriesData[this.selectedCountry];
    if (!countryData) return false;

    const cleanPhone = phone.trim().replace(/[^0-9+]/g, '');
    const { pattern, length } = countryData.phone;

    if (Array.isArray(length)) {
      const totalLength = cleanPhone.length;
      if (!length.includes(totalLength)) {
        console.log(`Invalid phone length: ${totalLength}, expected: ${length}`);
        return false;
      }
    } else {
      const totalLength = cleanPhone.length;
      const expectedLength = length + (cleanPhone.startsWith('+') ? 0 : 0);
      if (totalLength !== expectedLength) {
        console.log(`Invalid phone length: ${totalLength}, expected: ${expectedLength}`);
        return false;
      }
    }

    const regex = new RegExp(pattern);
    const isValid = regex.test(cleanPhone);
    if (!isValid) {
      console.log(`Phone validation failed:`, {
        phone: cleanPhone,
        pattern: pattern,
        country: this.selectedCountry
      });
    }
    return isValid;
  }

  attachEventListeners() {
    this.form.addEventListener('submit', (e) => this.handleSubmit(e));

    this.phoneCodeSelect.addEventListener('change', (e) => {
      const countryCode = e.target.value;
      const countryData = this.countriesData[countryCode];
      if (countryData) {
        const selectedFlag = this.phoneCodeSelect.parentElement.querySelector('.selected-flag');
        selectedFlag.src = countryData.flag;

        this.selectedPhoneCode = countryData.phone.code;
        this.selectedCountry = countryCode;

        this.phoneInput.value = this.selectedPhoneCode;
      }
    });

    this.phoneInput.addEventListener('input', (e) => {
      const cursorPosition = this.phoneInput.selectionStart;
      const codeLength = this.selectedPhoneCode.length;

      if (cursorPosition <= codeLength) {
        this.updatePhoneInputMask();
        this.phoneInput.setSelectionRange(codeLength, codeLength);
        return;
      }

      if (cursorPosition > codeLength) {
        const currentPosition = cursorPosition;
        this.updatePhoneInputMask();
        this.phoneInput.setSelectionRange(currentPosition, currentPosition);
      }
    });

    this.phoneInput.addEventListener('keydown', (e) => {
      const selectionStart = this.phoneInput.selectionStart;

      if (
        (e.key === 'Backspace' || e.key === 'Delete') &&
        selectionStart <= this.selectedPhoneCode.length
      ) {
        e.preventDefault();
      }
    });
  }

  async getBrowserLanguage() {
    try {
      const response = await fetch('languages.json');
      const languageMap = await response.json();

      return languageMap[this.userCountry]['language'];
    } catch (error) {
      console.error('Error getting browser language:', error);
      return 'EN';
    }
  }

  cleanURL(url) {
    try {
      let hostname = new URL(url).hostname;
      return hostname.replace(/^www\./, '');
    } catch (e) {
      return '';
    }
  }

  async checkDuplicate(email, phone, ip) {
    try {
      const submissionKey = `${email}|${phone}|${ip}`;
      
      if (this.submissionCache.has(submissionKey)) {
        return true;
      }
      
      // Используем cURL на стороне сервера для проверки дубликатов
      const backendUrl = window.backendServerUrl || '';
      
      // Используем локальный прокси для выполнения cURL запроса к бэкенд-серверу
      const response = await fetch('/curl-proxy.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify({
          method: 'POST',
          url: `${backendUrl}/form-handler.php`,
          data: { email, phone, ip, checkDuplicateOnly: true }
        }),
      });

      const result = await response.json();
      
      if (result.isDuplicate) {
        this.submissionCache.add(submissionKey);
      }
      
      return result.isDuplicate;

    } catch (error) {
      console.error('Error checking duplicate:', error);
      return false;
    }
  }

  async getUserIP() {
    try {
      const response = await fetch('https://api.ipify.org?format=json');
      const data = await response.json();
      return data.ip;
    } catch (error) {
      console.error('Error getting IP:', error);
      return null;
    }
  }

  async handleSubmit(e) {
    e.preventDefault();
    console.log("Form submission started");

    if (this.isSubmitting) {
      console.log("Already submitting, ignoring");
      return;
    }
    
    this.isSubmitting = true;
    console.log("Setting isSubmitting to true");

    try {
      const formData = new FormData(this.form);
      const email = formData.get('email');
      const phone = formData.get('phone');
      const ip = await this.getUserIP();

      const requiredFields = ['name', 'surname', 'email', 'phone'];
      for (const field of requiredFields) {
        if (!formData.get(field)) {
          this.showModal(`Будь ласка, заповніть поле ${field}`);
          this.isSubmitting = false;
          return;
        }
      }

      const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
      if (!emailRegex.test(email)) {
        this.showModal('Будь ласка, введіть коректну email адресу');
        this.isSubmitting = false;
        return;
      }

      if (!this.validatePhoneNumber(phone)) {
        const countryData = this.countriesData[this.selectedCountry];
        this.showModal(`Будь ласка, введіть коректний номер телефону для країни ${countryData.name}`);
        this.isSubmitting = false;
        return;
      }

      try {
        const jsonData = Object.fromEntries(formData.entries());
        
        // Переконуємося, що phone_code передається
        if (!jsonData.phone_code && this.selectedCountry) {
          jsonData.phone_code = this.selectedCountry;
        }
        
        // Переконуємося, що select_time передається
        const selectTimeElement = this.form.querySelector('select[name="select_time"]');
        if (selectTimeElement && (!jsonData.select_time || jsonData.select_time === 'Select Time')) {
          jsonData.select_time = selectTimeElement.options[selectTimeElement.selectedIndex].text;
        }
        
        // Переконуємося, що select_price передається
        const selectPriceElement = this.form.querySelector('select[name="select_price"]');
        if (selectPriceElement && !jsonData.select_price) {
          jsonData.select_price = selectPriceElement.options[selectPriceElement.selectedIndex].text;
        }
        
        jsonData.ip = ip;
        jsonData.title = document.title;
        jsonData.formId = this.form.id;
        jsonData.url = window.location.href;
        
        // Використовуємо локальний проксі для відправки cURL запиту до віддаленого сервера
        const backendUrl = window.backendServerUrl || '';
        
        const submitButton = this.form.querySelector('button[type="submit"]');
        submitButton.textContent = 'Надсилання...';
        submitButton.disabled = true;
        
        console.log('Відправка даних:', jsonData); // Додаємо для відлагодження
        
        const response = await fetch('/curl-proxy.php', {
          method: "POST",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify({
            method: 'POST',
            url: `${backendUrl}/form-handler.php`,
            data: jsonData
          })
        });

        const result = await response.json();
        console.log('Server response:', result); // Додаємо для відлагодження

        if (result.success) {
          this.submissionCache.add(`${email}|${phone}|${ip}`);
          
          // Отправка событий в аналитику
          const urlParams = new URLSearchParams(window.location.search);
          const gaId = urlParams.get('ga_id');
          if(gaId && typeof gtag === 'function') {
            gtag('event', 'form_submit', {
              'event_category': 'forms',
              'event_label': 'registration_form'
            });
          }
          
          const fbId = urlParams.get('fb_id');
          if(fbId && typeof fbq === 'function') {
            fbq('track', 'Lead');
          }
          
          // Очищаем форму
          this.form.reset();
          
          // Показываем модальное окно с перенаправлением
          this.showModal("Дані успішно відправлені. Зараз вас буде перенаправлено...", function() {
            window.location.href = "https://traffic-devils.webflow.io/";
          });
        } else {
          submitButton.textContent = 'Реєстрація';
          submitButton.disabled = false;
          this.showModal(result.message || "Помилка відправки форми");
        }
      } catch (error) {
        console.error("Request error:", error);
        submitButton.textContent = 'Реєстрація';
        submitButton.disabled = false;
        this.showModal("Не вдалося відправити дані. Спробуйте знову.");
      }
    } catch (error) {
      console.error('Submission error:', error);
      const submitButton = this.form.querySelector('button[type="submit"]');
      submitButton.textContent = 'Реєстрація';
      submitButton.disabled = false;
      this.showModal('Сталася помилка при відправці форми. Будь ласка, спробуйте знову.');
    } finally {
      this.isSubmitting = false;
      console.log("Setting isSubmitting to false");
    }
  }

  // Обновленный метод showModal
  showModal(message, callback = null) {
    const modal = document.getElementById('formResponseModal');
    const modalTitle = modal.querySelector('.modal-title');
    const modalBody = modal.querySelector('#formResponseMessage');
    
    modalTitle.textContent = 'Повідомлення';
    modalBody.textContent = message;
    
    // Используем jQuery для показа модального окна
    $('#formResponseModal').modal('show');
    
    if (callback) {
      // Удаляем предыдущие обработчики, чтобы избежать дублирования
      $('#formResponseModal').off('hidden.bs.modal');
      
      $('#formResponseModal').on('hidden.bs.modal', function() {
        callback();
        $('#formResponseModal').off('hidden.bs.modal');
      });
    }
  }
}

document.addEventListener('DOMContentLoaded', () => {
  const form1 = document.getElementById('registrationForm1');
  const phoneCode1 = document.getElementById('phoneCode1');
  if (form1 && phoneCode1) {
    new RegistrationForm('registrationForm1', 'phoneCode1');
  }

  const form2 = document.getElementById('registrationForm2');
  const phoneCode2 = document.getElementById('phoneCode2');
  if (form2 && phoneCode2) {
    new RegistrationForm('registrationForm2', 'phoneCode2');
  }
});

// Удаляем дублирующую функцию submitForm, так как она не используется
// Оставляем только глобальную функцию showModal для совместимости
function showModal(title, message, callback = null) {
  const modal = document.getElementById('formResponseModal');
  const modalTitle = modal.querySelector('.modal-title');
  const modalBody = modal.querySelector('#formResponseMessage');
  
  modalTitle.textContent = title;
  modalBody.textContent = message;
  
  // Используем Bootstrap для показа модального окна
  $('#formResponseModal').modal('show');
  
  if (callback) {
    // Удаляем предыдущие обработчики, чтобы избежать дублирования
    $('#formResponseModal').off('hidden.bs.modal');
    
    $('#formResponseModal').on('hidden.bs.modal', function() {
      callback();
      $('#formResponseModal').off('hidden.bs.modal');
    });
  }
}
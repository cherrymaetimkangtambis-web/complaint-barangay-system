(function () {
    'use strict';

    var navToggle = document.getElementById('nav-toggle');
    var mainNavigation = document.getElementById('main-navigation');
    if (navToggle && mainNavigation) {
        function closeNavigation() {
            mainNavigation.classList.remove('is-open');
            navToggle.classList.remove('is-open');
            navToggle.setAttribute('aria-expanded', 'false');
            navToggle.setAttribute('aria-label', 'Open navigation menu');
        }

        navToggle.addEventListener('click', function () {
            var open = mainNavigation.classList.toggle('is-open');
            navToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
            navToggle.setAttribute('aria-label', open ? 'Close navigation menu' : 'Open navigation menu');
            navToggle.classList.toggle('is-open', open);
        });
        mainNavigation.addEventListener('click', function (event) {
            if (event.target.closest('a')) {
                closeNavigation();
            }
        });
        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && mainNavigation.classList.contains('is-open')) {
                closeNavigation();
                navToggle.focus();
            }
        });
        document.addEventListener('click', function (event) {
            if (!event.target.closest('.topbar-inner')) {
                closeNavigation();
            }
        });
        window.addEventListener('resize', function () {
            if (window.innerWidth > 980) {
                closeNavigation();
            }
        });
    }

    var dialog = document.getElementById('accessibility-dialog');
    if (!dialog) {
        return;
    }

    var status = document.getElementById('access-status');
    var settings = {
        fontSize: document.getElementById('font-size'),
        contrast: document.getElementById('high-contrast'),
        readableFont: document.getElementById('dyslexia-font'),
        reduceMotion: document.getElementById('reduce-motion'),
        language: document.getElementById('site-language')
    };
    var storageKey = 'barangayAccessibility';
    var preferences = {};

    function announce(message) {
        status.textContent = message;
    }

    try {
        var stored = localStorage.getItem(storageKey);
        if (stored) {
            var parsed = JSON.parse(stored);
            if (parsed && typeof parsed === 'object' && !Array.isArray(parsed)) {
                preferences = parsed;
            }
        }
    } catch (error) {
        console.error('Could not read saved accessibility settings.', error);
    }

    function applySettings() {
        var root = document.documentElement;
        root.classList.toggle('font-large', settings.fontSize.value === 'large');
        root.classList.toggle('font-larger', settings.fontSize.value === 'larger');
        root.classList.toggle('high-contrast', settings.contrast.checked);
        root.classList.toggle('easy-read', settings.readableFont.checked);
        root.classList.toggle('reduce-motion', settings.reduceMotion.checked);
    }

    function saveSettings() {
        preferences = {
            fontSize: settings.fontSize.value,
            highContrast: settings.contrast.checked,
            readableFont: settings.readableFont.checked,
            reduceMotion: settings.reduceMotion.checked,
            language: settings.language.value
        };
        applySettings();
        try {
            localStorage.setItem(storageKey, JSON.stringify(preferences));
        } catch (error) {
            console.error('Could not save accessibility settings.', error);
            announce('Your settings could not be saved in this browser.');
        }
    }

    if (['normal', 'large', 'larger'].indexOf(preferences.fontSize) !== -1) {
        settings.fontSize.value = preferences.fontSize;
    }
    settings.contrast.checked = preferences.highContrast === true;
    settings.readableFont.checked = preferences.readableFont === true;
    settings.reduceMotion.checked = preferences.reduceMotion === true;
    if (preferences.language && Array.prototype.some.call(settings.language.options, function (option) {
        return option.value === preferences.language;
    })) {
        settings.language.value = preferences.language;
    }
    applySettings();

    Object.keys(settings).forEach(function (name) {
        settings[name].addEventListener('change', saveSettings);
    });

    var opener = document.getElementById('open-accessibility');
    var closer = document.getElementById('close-accessibility');
    opener.addEventListener('click', function () {
        if (typeof dialog.showModal === 'function') {
            dialog.showModal();
        } else {
            dialog.setAttribute('open', '');
        }
        document.getElementById('font-size').focus();
    });
    closer.addEventListener('click', function () {
        closeDialog();
    });
    dialog.addEventListener('click', function (event) {
        if (event.target === dialog) {
            closeDialog();
        }
    });
    dialog.addEventListener('cancel', function () {
        stopVoice();
    });

    function closeDialog() {
        stopVoice();
        if (typeof dialog.close === 'function') {
            dialog.close();
        } else {
            dialog.removeAttribute('open');
            opener.focus();
        }
    }

    var pageHelp = {
        'index.php': 'Start by registering or logging in. Residents can file and track concerns; barangay staff can review them.',
        'login.php': 'Enter your registered email and password. Use “Forgot password?” if you need to reset a resident account.',
        'register.php': 'Complete the form with your contact details and purok. Choose a password with at least eight characters.',
        'file_complaint.php': 'Choose a category, describe the issue, select its purok, and optionally attach a JPG or PNG photo.',
        'my_complaints.php': 'Find your complaints here. Open one to see its reference number, status history, and official remarks.',
        'view_complaint.php': 'Review the complaint details and status history. Pending complaints may have edit or cancel options.',
        'reports.php': 'Set a date range and choose Generate to review complaint totals. Use Print / Save as PDF for a printable report.',
        'complaints.php': 'Search or filter reports, then open a complaint to review details and update its status.',
        'notifications.php': 'Open a notification to see the related complaint and its latest update.',
        'dashboard.php': 'Use the summary cards and recent complaints to review activity. Navigation options depend on your account role.'
    };
    var pageName = document.body.getAttribute('data-page') || '';
    document.getElementById('page-help').textContent = pageHelp[pageName] || 'Use the navigation at the top to open the features available to your account.';

    var recognition = null;
    var listening = false;
    var SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;

    function stopVoice() {
        if (recognition && listening) {
            listening = false;
            recognition.stop();
        }
    }

    function setFontSize(size) {
        if (['normal', 'large', 'larger'].indexOf(size) !== -1) {
            settings.fontSize.value = size;
            saveSettings();
        }
    }

    function goTo(path) {
        var link = Array.prototype.find.call(document.querySelectorAll('.nav a, .brand'), function (item) {
            return (item.getAttribute('href') || '').endsWith(path);
        });
        if (link) {
            window.location.href = link.href;
            return true;
        }
        return false;
    }

    function handleCommand(transcript) {
        var phrase = transcript.toLocaleLowerCase().replace(/[.!?]/g, '').trim();
        var navCommands = [
            { terms: ['dashboard', 'open dashboard', 'go to dashboard', 'buksan ang dashboard'], path: 'dashboard.php' },
            { terms: ['file complaint', 'new complaint', 'file a complaint', 'mag-file ng reklamo'], path: 'file_complaint.php' },
            { terms: ['my complaints', 'complaints ko', 'reklamo ko'], path: 'my_complaints.php' },
            { terms: ['reports', 'open reports'], path: 'reports.php' },
            { terms: ['notifications', 'open notifications'], path: 'notifications.php' },
            { terms: ['home', 'go home', 'open home'], path: 'index.php' }
        ];
        var command = navCommands.find(function (item) {
            return item.terms.some(function (term) {
                return phrase === term;
            });
        });

        if (phrase === 'help' || phrase === 'open help') {
            announce('Help and accessibility settings are open.');
            return;
        }
        if (phrase === 'increase text' || phrase === 'larger text') {
            setFontSize(settings.fontSize.value === 'normal' ? 'large' : 'larger');
            announce('Text size increased.');
            return;
        }
        if (phrase === 'decrease text' || phrase === 'smaller text') {
            setFontSize(settings.fontSize.value === 'larger' ? 'large' : 'normal');
            announce('Text size decreased.');
            return;
        }
        if (phrase === 'stop reading') {
            if ('speechSynthesis' in window) {
                window.speechSynthesis.cancel();
            }
            announce('Reading stopped.');
            return;
        }
        if (phrase === 'stop listening' || phrase === 'stop voice commands') {
            stopVoice();
            announce('Voice commands stopped.');
            return;
        }
        if (command) {
            if (!goTo(command.path)) {
                announce('That page is not available for this account.');
            }
            return;
        }
        announce('Command not recognized. Try “help,” “open dashboard,” or “file complaint.”');
    }

    function startVoice() {
        if (!SpeechRecognition) {
            announce('Voice commands are not supported by this browser. Try a current version of Chrome or Edge.');
            return;
        }
        if (!recognition) {
            recognition = new SpeechRecognition();
            recognition.continuous = true;
            recognition.interimResults = false;
            recognition.onstart = function () {
                listening = true;
                announce('Listening. Say a command, or say “stop listening.”');
            };
            recognition.onresult = function (event) {
                var result = event.results[event.results.length - 1];
                if (result && result[0]) {
                    handleCommand(result[0].transcript);
                }
            };
            recognition.onerror = function (event) {
                listening = false;
                var messages = {
                    'not-allowed': 'Microphone access was denied. Allow microphone access in your browser settings to use voice commands.',
                    'service-not-allowed': 'The browser speech service is unavailable.',
                    'language-not-supported': 'This browser does not support voice recognition for the selected language.',
                    'network': 'Voice recognition needs an internet connection in this browser.',
                    'no-speech': 'No speech was detected. Select Start voice commands to try again.'
                };
                announce(messages[event.error] || 'Voice recognition stopped because of a browser error: ' + event.error + '.');
            };
            recognition.onend = function () {
                if (listening) {
                    listening = false;
                    announce('Voice commands stopped. Select Start voice commands to begin again.');
                }
            };
        }
        recognition.lang = settings.language.value === 'und-PH' ? 'en-PH' : settings.language.value;
        listening = true;
        try {
            recognition.start();
        } catch (error) {
            listening = false;
            console.error('Could not start voice recognition.', error);
            announce('Voice recognition could not start. Stop any active session and try again.');
        }
    }

    document.getElementById('start-voice').addEventListener('click', startVoice);
    document.getElementById('stop-voice').addEventListener('click', function () {
        stopVoice();
        announce('Voice commands stopped.');
    });
    document.getElementById('read-page').addEventListener('click', function () {
        if (!('speechSynthesis' in window) || !('SpeechSynthesisUtterance' in window)) {
            announce('Read aloud is not supported by this browser.');
            return;
        }
        var main = document.getElementById('main-content');
        var text = main ? main.innerText.replace(/\s+/g, ' ').trim() : '';
        if (!text) {
            announce('There is no page text to read.');
            return;
        }
        window.speechSynthesis.cancel();
        var utterance = new SpeechSynthesisUtterance(text.slice(0, 12000));
        utterance.lang = 'en-PH';
        utterance.onend = function () {
            announce('Finished reading the page.');
        };
        utterance.onerror = function (event) {
            announce('Read aloud stopped because of a browser error: ' + event.error + '.');
        };
        window.speechSynthesis.speak(utterance);
        announce(text.length > 12000 ? 'Reading the first part of this page aloud.' : 'Reading this page aloud.');
    });
    document.getElementById('stop-reading').addEventListener('click', function () {
        if ('speechSynthesis' in window) {
            window.speechSynthesis.cancel();
        }
        announce('Reading stopped.');
    });
}());

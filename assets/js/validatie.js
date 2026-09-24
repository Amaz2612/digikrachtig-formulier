document.addEventListener("DOMContentLoaded", () => {
    console.log("Validatie.js is succesvol geladen!");

    // 1. MAAK ALLE VERVOLGVRAGEN STANDAARD VERPLICHT
    // (De 'Volgende' knop controleert ze later toch alleen als ze zichtbaar zijn)
    document.querySelectorAll('.conditional-field input, .conditional-field textarea').forEach(el => {
        if (el.type !== 'checkbox') {
            el.setAttribute('required', 'required');
        }
    });

    // --- 2. NAVIGATIE TUSSEN STAPPEN ---
    const steps = document.querySelectorAll('.form-step');
    const nextButtons = document.querySelectorAll('.btn-next');
    const prevButtons = document.querySelectorAll('.btn-prev');
    let currentStep = 0;

    // Volgende knop functionaliteit
    nextButtons.forEach(button => {
        button.addEventListener('click', () => {
            const currentFieldset = steps[currentStep];
            const inputs = currentFieldset.querySelectorAll('input, select, textarea');
            let isValid = true;

            for (let input of inputs) {
                // Cruciaal: als het veld onzichtbaar is, wordt de validatie overgeslagen
                if (input.offsetParent === null) continue; 

                if (!input.checkValidity()) {
                    isValid = false;
                    input.reportValidity(); // Toont de melding "Vul dit veld in"
                    break; 
                }
            }

            if (isValid) {
                steps[currentStep].classList.remove('active');
                currentStep++;
                steps[currentStep].classList.add('active');
                window.scrollTo(0, 0); // Scroll netjes terug naar boven voor de nieuwe stap
            }
        });
    });

    // Vorige knop functionaliteit
    prevButtons.forEach(button => {
        button.addEventListener('click', () => {
            steps[currentStep].classList.remove('active');
            currentStep--;
            steps[currentStep].classList.add('active');
            window.scrollTo(0, 0);
        });
    });

    // --- 3. CONDITIONELE LOGICA (IN- EN UITKLAPPEN) ---
    function setupToggle(radioName, jaDivId, neeDivId) {
        const radios = document.querySelectorAll(`input[name="${radioName}"]`);
        const jaDiv = jaDivId ? document.getElementById(jaDivId) : null;
        const neeDiv = neeDivId ? document.getElementById(neeDivId) : null;

        radios.forEach(radio => {
            radio.addEventListener('change', (e) => {
                if (e.target.value === 'ja') {
                    if (jaDiv) jaDiv.style.display = 'block';
                    if (neeDiv) neeDiv.style.display = 'none';
                } else if (e.target.value === 'nee') {
                    if (neeDiv) neeDiv.style.display = 'block';
                    if (jaDiv) jaDiv.style.display = 'none';
                }
            });
        });
    }

    // Microsoft 365 (Stap 2) heeft 3 opties in plaats van 2, dus deze doen we handmatig
    const m365Radios = document.querySelectorAll('input[name="m365_usage"]');
    const m365NeeVragen = document.getElementById('m365_nee_vragen');
    m365Radios.forEach(radio => {
        radio.addEventListener('change', (e) => {
            if (e.target.value === 'nee') {
                m365NeeVragen.style.display = 'block';
            } else {
                m365NeeVragen.style.display = 'none';
            }
        });
    });

    // Koppel alle standaard Ja/Nee blokken
    const softwareTools = ['word', 'excel', 'ppt', 'outlook', 'teams', 'onedrive'];
    softwareTools.forEach(tool => {
        setupToggle(`${tool}_active`, `${tool}_ja_vragen`, `${tool}_nee_vragen`);
    });

    setupToggle('website_active', 'website_ja_vragen', 'website_nee_vragen');
    setupToggle('website_tevreden', null, 'website_tevreden_nee_vragen');
    setupToggle('website_interesse', 'website_interesse_ja_vragen', null);

    setupToggle('social_active', 'social_ja_vragen', 'social_nee_vragen');
    setupToggle('social_tevreden', null, 'social_tevreden_nee_vragen');
    setupToggle('social_interesse', 'social_interesse_ja_vragen', null);

    setupToggle('ai_active', 'ai_ja_vragen', 'ai_nee_vragen');

    // --- 4. BEVEILIGING TEGEN VROEGTIJDIG VERZENDEN ---
    const form = document.getElementById('digikrachtig-form');
    if (form) {
        form.addEventListener('submit', (e) => {
            // Als de gebruiker op "Enter" drukt maar nog niet bij de laatste stap is, blokkeer dan het verzenden
            if (currentStep < steps.length - 1) {
                e.preventDefault();
            }
        });
    }
});
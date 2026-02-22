document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('userFeedbackForm');
    const message = document.getElementById('message');
    const category = document.getElementById('category');
    const district = document.getElementById('district');
    const barangay = document.getElementById('barangay');
    const altName = document.getElementById('alt_name');
    const locationInput = document.getElementById('location');
    const gpsPinBtn = document.getElementById('gpsPinBtn');
    const improveAccuracyBtn = document.getElementById('improveAccuracyBtn');
    const gpsLat = document.getElementById('gps_lat');
    const gpsLng = document.getElementById('gps_lng');
    const gpsAccuracy = document.getElementById('gps_accuracy');
    const gpsMapUrl = document.getElementById('gps_map_url');
    const photoInput = document.getElementById('photo');
    const removePhotoBtn = document.getElementById('removePhotoBtn');
    const photoStatus = document.getElementById('photoStatus');
    const mapContainer = document.getElementById('concernMap');
    const mapSearchInput = document.getElementById('mapSearchInput');
    const mapSearchBtn = document.getElementById('mapSearchBtn');
    const pinnedAddress = document.getElementById('pinnedAddress');
    const gpsAddress = document.getElementById('gps_address');
    const draftKey = 'ipms_feedback_form_draft_v1';

    if (!form) return;

    const districtData = {
        '1': {
            'Alicia': ['Bago Bantay'],
            'Bagong Pag-asa': ['North-EDSA', 'Diliman (southern part)', 'Triangle Park (southern triangle)'],
            'Bahay Toro': ['Project 8', 'Pugadlawin'],
            'Balingasa': ['Balintawak', 'Cloverleaf'],
            'Bungad': ['Project 7'],
            'Damar': ['Balintawak'],
            'Damayan': ['San Francisco del Monte', 'Frisco'],
            'Del Monte': ['San Francisco del Monte', 'Frisco'],
            'Katipunan': ['Munoz'],
            'Lourdes': ['Santa Mesa Heights'],
            'Maharlika': ['Santa Mesa Heights'],
            'Manresa': ['Balintawak', 'San Francisco del Monte', 'Frisco'],
            'Mariblo': ['San Francisco del Monte', 'Frisco'],
            'Masambong': ['San Francisco del Monte', 'Frisco'],
            'N.S. Amoranto (Gintong Silahis)': ['La Loma'],
            'Nayong Kanluran': ['West Avenue'],
            'Paang Bundok': ['La Loma'],
            'Pag-ibig sa Nayon': ['Balintawak'],
            'Paltok': ['San Francisco del Monte', 'Frisco'],
            'Paraiso': ['San Francisco del Monte', 'Frisco'],
            'Phil-Am': ['West Triangle', 'Diliman'],
            'Project 6': ['Diliman (southeast quarter)', 'Triangle Park (southern half)'],
            'Ramon Magsaysay': ['Bago Bantay', 'Munoz'],
            'Saint Peter': ['Santa Mesa Heights'],
            'Salvacion': ['La Loma'],
            'San Antonio': ['San Francisco del Monte', 'Frisco'],
            'San Isidro Labrador': ['La Loma'],
            'San Jose': ['La Loma'],
            'Santa Cruz': ['Pantranco', 'Heroes Hill'],
            'Santa Teresita': ['Santa Mesa Heights'],
            'Sto. Cristo': ['Bago Bantay'],
            'Santo Domingo (Matalahib)': ['Matalahib', 'Santa Mesa Heights'],
            'Siena': ['Santa Mesa Heights'],
            'Talayan': ['San Francisco del Monte', 'Frisco'],
            'Vasra': ['Diliman (mostly)'],
            'Veterans Village': ['Project 7', 'Munoz'],
            'West Triangle': ['Diliman']
        },
        '2': {
            'Bagong Silangan': ['Payatas'],
            'Batasan Hills': ['Constitution Hills'],
            'Commonwealth': ['Manggahan', 'Litex'],
            'Holy Spirit': ['Don Antonio', 'Luzon'],
            'Payatas': ['Litex']
        },
        '3': {
            'Amihan': ['Project 3'],
            'Bagumbayan': ['Eastwood', 'Acropolis', 'Citybank', 'Gentex', 'Libis'],
            'Bagumbuhay': ['Project 4'],
            'Bayanihan': ['Project 4'],
            'Blue Ridge A': ['Project 4'],
            'Blue Ridge B': ['Project 4'],
            'Camp Aguinaldo': ['Armed Forces (AFP)', 'Murphy'],
            'Claro (Quirino 3-B)': ['Project 3'],
            'Dioquino Zobel': ['Project 4'],
            'Duyan-duyan': ['Project 3'],
            'E. Rodriguez': ['Project 5', 'Cubao'],
            'East Kamias': ['Project 1', 'Kamias'],
            'Escopa I': ['Project 4'],
            'Escopa II': ['Project 4'],
            'Escopa III': ['Project 4'],
            'Escopa IV': ['Project 4'],
            'Libis': ['Camp Atienza', 'Eastwood'],
            'Loyola Heights': ['Katipunan'],
            'Mangga': ['Cubao', 'Anonas', 'T.I.P.'],
            'Marilag': ['Project 4'],
            'Masagana': ['Project 4', 'Jacobo Zobel'],
            'Matandang Balara': ['Old Balara', 'Luzon', 'Tandang Sora'],
            'Milagrosa': ['Project 4'],
            'Pansol': ['Balara', 'Katipunan'],
            'Quirino 2-A': ['Project 2', 'Anonas'],
            'Quirino 2-B': ['Project 2', 'Anonas'],
            'Quirino 2-C': ['Project 2', 'Anonas'],
            'Quirino 3-A': ['Project 3', 'Anonas'],
            'St. Ignatius': ['Project 4', 'Katipunan'],
            'San Roque': ['Cubao'],
            'Silangan': ['Cubao'],
            'Socorro': ['Cubao', 'Araneta City'],
            'Tagumpay': ['Project 4'],
            'Ugong Norte': ['Green Meadows', 'Corinthian', 'Ortigas'],
            'Villa Maria Clara': ['Project 4'],
            'West Kamias': ['Project 5', 'Kamias'],
            'White Plains': ['Camp Aguinaldo', 'Katipunan']
        },
        '4': {
            'Bagong Lipunan ng Crame': ['Camp Crame', 'Philippine National Police (PNP)'],
            'Botocan': ['Diliman (northern half)'],
            'Central': ['Diliman', 'Quezon City Hall'],
            'Damayang Lagi': ['New Manila'],
            'Don Manuel': ['Galas'],
            'Dona Aurora': ['Galas'],
            'Dona Imelda': ['Galas', 'Sta. Mesa (border with City of Manila)'],
            'Dona Josefa': ['Galas'],
            'Horseshoe': ['New Manila'],
            'Immaculate Concepcion': ['Cubao'],
            'Kalusugan': ["St. Luke's"],
            'Kamuning': ['Project 1', 'Scout Area'],
            'Kaunlaran': ['Cubao'],
            'Kristong Hari': ['E. Rodriguez', 'New Manila'],
            'Krus na Ligas': ['Diliman'],
            'Laging Handa': ['Diliman', 'Scout Area'],
            'Malaya': ['Diliman'],
            'Mariana': ['New Manila'],
            'Obrero': ['Diliman (northern half)', 'Project 1 (southern half)'],
            'Old Capitol Site': ['Diliman'],
            'Paligsahan': ['Diliman', 'Scout Area'],
            'Pinagkaisahan': ['Cubao'],
            'Pinyahan': ['Diliman', 'Triangle Park (northern triangle)'],
            'Roxas': ['Project 1'],
            'Sacred Heart': ['Kamuning', 'Diliman', 'Scout Area'],
            'San Isidro Galas': ['Galas'],
            'San Martin de Porres': ['Cubao', 'Arayat'],
            'San Vicente': ['Diliman', 'UP Bliss'],
            'Santol': ['Galas'],
            'Sikatuna Village': ['Diliman'],
            'South Triangle': ['Diliman', 'Scout Area'],
            'Santo Nino': ['Galas'],
            'Tatalon': ['Sanctuarium', 'Araneta Avenue'],
            "Teacher's Village East": ['Diliman'],
            "Teacher's Village West": ['Diliman'],
            'U.P. Campus': ['Diliman'],
            'U.P. Village': ['Diliman'],
            'Valencia': ['New Manila', 'Gilmore Ave.', 'N. Domingo Ave.']
        },
        '5': {
            'Bagbag': ['Novaliches District', 'Sauyo'],
            'Capri': ['Novaliches District'],
            'Fairview': ['Novaliches District', 'La Mesa', 'West Fairview'],
            'Gulod': ['Novaliches District', 'Susano', 'Nitang'],
            'Greater Lagro': ['Novaliches District', 'Lagro', 'Fairview'],
            'Kaligayahan': ['Novaliches District', 'Zabarte'],
            'Nagkaisang Nayon': ['Novaliches District', 'General Luis'],
            'North Fairview': ['Novaliches District'],
            'Novaliches Proper': ['Novaliches Bayan', 'Glori', 'Bayan'],
            'Pasong Putik Proper': ['Novaliches District', 'Maligaya Drive', 'Fairview'],
            'San Agustin': ['Novaliches District', 'Susano'],
            'San Bartolome': ['Novaliches District', 'Holy Cross'],
            'Sta. Lucia': ['Novaliches District', 'San Gabriel'],
            'Sta. Monica': ['Novaliches District']
        },
        '6': {
            'Apolonio Samson': ['Balintawak', 'Kaingin', 'Kangkong'],
            'Baesa': ['Project 8', 'Novaliches District'],
            'Balon Bato': ['Balintawak'],
            'Culiat': ['Tandang Sora'],
            'New Era': ['Iglesia ni Cristo/Central', 'Tandang Sora'],
            'Pasong Tamo': ['Pingkian', 'Philand'],
            'Sangandaan': ['Project 8'],
            'Sauyo': ['Novaliches District'],
            'Talipapa': ['Novaliches District'],
            'Tandang Sora': ['Banlat'],
            'Unang Sigaw': ['Balintawak', 'Cloverleaf']
        }
    };

    if (category) {
        category.addEventListener('mousedown', function () {
            category.dataset.scrollY = String(window.scrollY || 0);
        });
        category.addEventListener('change', function () {
            const y = parseInt(category.dataset.scrollY || '0', 10);
            window.requestAnimationFrame(function () {
                window.scrollTo({ top: Number.isNaN(y) ? 0 : y, behavior: 'auto' });
            });
        });
    }
    form.addEventListener('input', saveDraft);
    form.addEventListener('change', saveDraft);
    restoreDraft();

    function showMessage(text, ok) {
        if (!message) return;
        message.style.display = 'block';
        message.textContent = text;
        message.style.padding = '10px 12px';
        message.style.borderRadius = '8px';
        message.style.border = ok ? '1px solid #86efac' : '1px solid #fecaca';
        message.style.background = ok ? '#dcfce7' : '#fee2e2';
        message.style.color = ok ? '#166534' : '#991b1b';
    }

    function saveDraft() {
        if (!window.localStorage || !form) return;
        const subjectEl = document.getElementById('subject');
        const feedbackEl = document.getElementById('feedback');
        const completeAddressEl = document.getElementById('complete_address');
        const payload = {
            subject: subjectEl ? subjectEl.value : '',
            district: district ? district.value : '',
            barangay: barangay ? barangay.value : '',
            alt_name: altName ? altName.value : '',
            category: category ? category.value : '',
            complete_address: completeAddressEl ? completeAddressEl.value : '',
            feedback: feedbackEl ? feedbackEl.value : ''
        };
        try {
            window.localStorage.setItem(draftKey, JSON.stringify(payload));
        } catch (_error) {
            // ignore storage errors
        }
    }

    function restoreDraft() {
        if (!window.localStorage) return;
        let raw = '';
        try {
            raw = window.localStorage.getItem(draftKey) || '';
        } catch (_error) {
            return;
        }
        if (!raw) return;

        let draft = null;
        try {
            draft = JSON.parse(raw);
        } catch (_error) {
            return;
        }
        if (!draft || typeof draft !== 'object') return;

        const subjectEl = document.getElementById('subject');
        const feedbackEl = document.getElementById('feedback');
        const completeAddressEl = document.getElementById('complete_address');
        if (subjectEl && draft.subject) subjectEl.value = draft.subject;
        if (category && draft.category) category.value = draft.category;
        if (completeAddressEl && draft.complete_address) completeAddressEl.value = draft.complete_address;
        if (feedbackEl && draft.feedback) feedbackEl.value = draft.feedback;

        if (district && draft.district) {
            district.value = draft.district;
            district.dispatchEvent(new Event('change'));
            setTimeout(function () {
                if (barangay && draft.barangay) {
                    barangay.value = draft.barangay;
                    barangay.dispatchEvent(new Event('change'));
                }
                setTimeout(function () {
                    if (altName && draft.alt_name) {
                        altName.value = draft.alt_name;
                        altName.dispatchEvent(new Event('change'));
                    }
                }, 150);
            }, 150);
        }
    }

    function resetSelect(select, placeholder) {
        if (!select) return;
        select.innerHTML = '';
        const defaultOption = document.createElement('option');
        defaultOption.value = '';
        defaultOption.textContent = placeholder;
        select.appendChild(defaultOption);
        select.value = '';
    }


    async function reverseGeocode(lat, lng) {
        try {
            const url = 'https://nominatim.openstreetmap.org/reverse?format=jsonv2&lat=' + encodeURIComponent(String(lat)) + '&lon=' + encodeURIComponent(String(lng));
            const response = await fetch(url, {
                headers: { 'Accept': 'application/json' }
            });
            if (!response.ok) return '';
            const data = await response.json();
            return (data && data.display_name) ? String(data.display_name) : '';
        } catch (_error) {
            return '';
        }
    }

    async function searchLocation(query) {
        const url = 'https://nominatim.openstreetmap.org/search?format=jsonv2&limit=1&q=' + encodeURIComponent(query);
        const response = await fetch(url, {
            headers: { 'Accept': 'application/json' }
        });
        if (!response.ok) return null;
        const data = await response.json();
        if (!Array.isArray(data) || data.length === 0) return null;
        return {
            lat: parseFloat(data[0].lat),
            lng: parseFloat(data[0].lon),
            address: data[0].display_name || ''
        };
    }

    function buildLocationValue() {
        if (!locationInput || !district || !barangay || !altName) return;
        const districtText = district.value ? 'District ' + district.value : '';
        const barangayText = barangay.value || '';
        const altText = altName.value || '';

        if (districtText && barangayText && altText) {
            locationInput.value = districtText + ' | ' + barangayText + ' | ' + altText;
        } else {
            locationInput.value = '';
        }
    }

    async function pinToLocation(lat, lng, accuracyMeters) {
        if (map) {
            map.setView([lat, lng], 17);
            if (!marker) {
                marker = window.L.marker([lat, lng], { draggable: true }).addTo(map);
                marker.on('dragend', function (dragEvent) {
                    const p = dragEvent.target.getLatLng();
                    updateMapFields(p.lat, p.lng, null);
                    buildLocationValue();
                });
            } else {
                marker.setLatLng([lat, lng]);
            }
        }
        await updateMapFields(lat, lng, accuracyMeters);
        buildLocationValue();
    }

    async function resolveAndPinSelection() {
        if (!district || !barangay || !altName) return;
        const districtValue = district.value || '';
        const barangayValue = barangay.value || '';
        const altValue = altName.value || '';
        if (!districtValue || !barangayValue) return;

        const query = [altValue, barangayValue, 'District ' + districtValue, 'Quezon City, Metro Manila, Philippines']
            .filter(Boolean)
            .join(', ');
        try {
            const result = await searchLocation(query);
            if (!result) return;
            await pinToLocation(result.lat, result.lng, null);
            if (pinnedAddress && result.address) pinnedAddress.textContent = result.address;
            if (gpsAddress && result.address) gpsAddress.value = result.address;
        } catch (_error) {
            // Ignore geocoding errors; keep form interactive.
        }
    }

    function getBestCurrentLocation() {
        return new Promise(function (resolve, reject) {
            if (!navigator.geolocation) {
                reject(new Error('Geolocation is not supported by your browser.'));
                return;
            }
            let best = null;
            let watchId = null;
            const timeoutMs = 8000;
            const startedAt = Date.now();

            function finish() {
                if (watchId !== null) navigator.geolocation.clearWatch(watchId);
                if (best) resolve(best);
                else reject(new Error('Unable to get your current location.'));
            }

            watchId = navigator.geolocation.watchPosition(function (position) {
                const candidate = {
                    lat: position.coords.latitude,
                    lng: position.coords.longitude,
                    accuracy: position.coords.accuracy || null
                };
                if (!best || (candidate.accuracy && best.accuracy && candidate.accuracy < best.accuracy) || (!best.accuracy && candidate.accuracy)) {
                    best = candidate;
                }
                if (candidate.accuracy && candidate.accuracy <= 15) {
                    finish();
                    return;
                }
                if (Date.now() - startedAt >= timeoutMs) finish();
            }, function (error) {
                reject(error);
            }, {
                enableHighAccuracy: true,
                timeout: timeoutMs,
                maximumAge: 0
            });

            setTimeout(finish, timeoutMs + 200);
        });
    }

    if (district && barangay && altName && locationInput) {
        district.addEventListener('change', function () {
            const selectedDistrict = district.value;
            const barangayData = districtData[selectedDistrict] || {};
            const barangays = Object.keys(barangayData).sort(function (a, b) {
                return a.localeCompare(b);
            });

            resetSelect(barangay, 'Select Barangay');
            resetSelect(altName, 'Select Alternative Name');
            altName.disabled = true;

            if (barangays.length === 0) {
                barangay.disabled = true;

                buildLocationValue();
                return;
            }

            barangay.disabled = false;
            barangays.forEach(function (name) {
                const option = document.createElement('option');
                option.value = name;
                option.textContent = name;
                barangay.appendChild(option);
            });

            buildLocationValue();
            resolveAndPinSelection();
        });

        barangay.addEventListener('change', function () {
            const selectedDistrict = district.value;
            const selectedBarangay = barangay.value;
            const altNames = (districtData[selectedDistrict] && districtData[selectedDistrict][selectedBarangay]) || [];

            resetSelect(altName, 'Select Alternative Name');
            if (altNames.length === 0) {
                altName.disabled = true;
                buildLocationValue();
                return;
            }

            altName.disabled = false;
            altNames.forEach(function (name) {
                const option = document.createElement('option');
                option.value = name;
                option.textContent = name;
                altName.appendChild(option);
            });

            buildLocationValue();
            resolveAndPinSelection();
        });

        altName.addEventListener('change', function () {
            buildLocationValue();
            resolveAndPinSelection();
        });
    }


    if (gpsPinBtn) {
        gpsPinBtn.addEventListener('click', async function () {
            try {
                const best = await getBestCurrentLocation();
                await pinToLocation(best.lat, best.lng, best.accuracy);
                const accText = best.accuracy ? Math.round(best.accuracy) + 'm' : 'unknown';
                showMessage('Used your GPS as a starting pin (~' + accText + ' accuracy). Drag the pin for exact spot.', true);
            } catch (error) {
                const msg = error && error.message ? error.message : 'Unable to get your current location.';
                showMessage(msg, false);
            }
        });
    }

    if (improveAccuracyBtn) {
        improveAccuracyBtn.addEventListener('click', async function () {
            try {
                const best = await getBestCurrentLocation();
                await pinToLocation(best.lat, best.lng, best.accuracy);
                const accText = best.accuracy ? Math.round(best.accuracy) + 'm' : 'unknown';
                showMessage('GPS accuracy improved (~' + accText + ').', true);
            } catch (error) {
                const msg = error && error.message ? error.message : 'Unable to improve GPS accuracy right now.';
                showMessage(msg, false);
            }
        });
    }

    async function updateMapFields(lat, lng, accuracyMeters) {
        const latText = Number(lat).toFixed(6);
        const lngText = Number(lng).toFixed(6);
        if (gpsLat) gpsLat.value = latText;
        if (gpsLng) gpsLng.value = lngText;
        if (gpsAccuracy) gpsAccuracy.value = accuracyMeters ? String(Math.round(accuracyMeters)) : '';
        if (gpsMapUrl) gpsMapUrl.value = 'https://www.openstreetmap.org/?mlat=' + latText + '&mlon=' + lngText + '#map=18/' + latText + '/' + lngText;
        const addressText = await reverseGeocode(latText, lngText);
        if (gpsAddress) gpsAddress.value = addressText;
        if (pinnedAddress) pinnedAddress.textContent = addressText !== '' ? addressText : 'Pinned location found, but address is unavailable.';
    }

    if (photoInput && photoStatus) {
        photoInput.addEventListener('change', function () {
            photoStatus.textContent = photoInput.files && photoInput.files.length > 0
                ? 'Selected: ' + photoInput.files[0].name
                : 'No photo selected.';
        });
    }

    if (removePhotoBtn && photoInput) {
        removePhotoBtn.addEventListener('click', function () {
            photoInput.value = '';
            if (photoStatus) photoStatus.textContent = 'No photo selected.';
        });
    }

    let map = null;
    let marker = null;
    if (mapContainer && typeof window.L !== 'undefined') {
        map = window.L.map(mapContainer).setView([14.6760, 121.0437], 12);
        const streets = window.L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '&copy; OpenStreetMap contributors'
        }).addTo(map);
        const satellite = window.L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
            maxZoom: 19,
            attribution: 'Tiles &copy; Esri'
        });
        window.L.control.layers(
            { Streets: streets, Satellite: satellite },
            {},
            { position: 'topright', collapsed: true }
        ).addTo(map);

        // Approximate Quezon City boundary guide for quick visual orientation.
        const qcBoundary = window.L.polygon([
            [14.7903, 121.0178],
            [14.7901, 121.0909],
            [14.7717, 121.1088],
            [14.7429, 121.1156],
            [14.7137, 121.1097],
            [14.6844, 121.1062],
            [14.6542, 121.0925],
            [14.6332, 121.0746],
            [14.6218, 121.0469],
            [14.6196, 121.0204],
            [14.6327, 121.0014],
            [14.6561, 120.9970],
            [14.6904, 120.9973],
            [14.7191, 121.0013],
            [14.7484, 121.0076],
            [14.7725, 121.0114]
        ], {
            color: '#2563eb',
            weight: 2,
            fillColor: '#3b82f6',
            fillOpacity: 0.05
        }).addTo(map);
        qcBoundary.bindTooltip('Approximate Quezon City boundary', { sticky: true });

        map.on('click', function (event) {
            const lat = event.latlng.lat;
            const lng = event.latlng.lng;
            pinToLocation(lat, lng, null);
        });
    }


    if (mapSearchBtn && mapSearchInput) {
        mapSearchBtn.addEventListener('click', async function () {
            const query = (mapSearchInput.value || '').trim();
            if (query === '') {
                showMessage('Enter a location to search on the map.', false);
                return;
            }

            try {
                const result = await searchLocation(query);
                if (!result) {
                    showMessage('No matching location found.', false);
                    return;
                }

                if (map) {
                    await pinToLocation(result.lat, result.lng, null);
                }

                if (pinnedAddress && result.address) {
                    pinnedAddress.textContent = result.address;
                }
                if (gpsAddress && result.address) {
                    gpsAddress.value = result.address;
                }

                await updateMapFields(result.lat, result.lng, null);
                buildLocationValue();
            } catch (_error) {
                showMessage('Unable to search location right now.', false);
            }
        });

        mapSearchInput.addEventListener('keydown', function (event) {
            if (event.key === 'Enter') {
                event.preventDefault();
                mapSearchBtn.click();
            }
        });
    }


    const photoModal = document.getElementById('feedbackPhotoModal');
    const photoPreview = document.getElementById('feedbackPhotoPreview');
    const photoClose = document.getElementById('feedbackPhotoClose');
    const detailsModal = document.getElementById('feedbackDetailsModal');
    const detailsClose = document.getElementById('feedbackDetailsClose');
    const fdDate = document.getElementById('fdDate');
    const fdSubject = document.getElementById('fdSubject');
    const fdCategory = document.getElementById('fdCategory');
    const fdLocation = document.getElementById('fdLocation');
    const fdStatus = document.getElementById('fdStatus');
    const fdDescription = document.getElementById('fdDescription');
    const fdRejectionNoteRow = document.getElementById('fdRejectionNoteRow');
    const fdRejectionNote = document.getElementById('fdRejectionNote');
    const fdPhotoRow = document.getElementById('fdPhotoRow');
    const fdViewPhotoBtn = document.getElementById('fdViewPhotoBtn');
    let currentDetailsPhotoUrl = '';

    document.querySelectorAll('.feedback-photo-view-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            const url = btn.getAttribute('data-photo-url') || '';
            if (!photoModal || !photoPreview || url === '') return;
            if (detailsModal) detailsModal.style.zIndex = '1300';
            photoModal.style.zIndex = '1400';
            photoPreview.src = url;
            photoModal.hidden = false;
            document.body.style.overflow = 'hidden';
        });
    });

    function closePhotoModal() {
        if (!photoModal) return;
        photoModal.hidden = true;
        if (photoPreview) photoPreview.src = '';
        document.body.style.overflow = '';
    }

    if (photoClose) {
        photoClose.addEventListener('click', closePhotoModal);
    }

    if (photoModal) {
        photoModal.addEventListener('click', function (event) {
            if (event.target === photoModal) {
                closePhotoModal();
            }
        });
    }

    document.querySelectorAll('[data-feedback-open="1"]').forEach(function (row) {
        row.setAttribute('tabindex', '0');
        row.setAttribute('role', 'button');
        row.addEventListener('click', function (event) {
            if (event.target && event.target.closest('.feedback-photo-view-btn')) {
                return;
            }
            if (!detailsModal) return;
            if (fdDate) fdDate.textContent = row.getAttribute('data-date') || '-';
            if (fdSubject) fdSubject.textContent = row.getAttribute('data-subject') || '-';
            if (fdCategory) fdCategory.textContent = row.getAttribute('data-category') || '-';
            if (fdLocation) fdLocation.textContent = row.getAttribute('data-location') || '-';
            if (fdStatus) fdStatus.textContent = row.getAttribute('data-status') || '-';
            const descriptionText = row.getAttribute('data-description') || '-';
            if (fdDescription) fdDescription.textContent = descriptionText;
            let rejectionNote = (row.getAttribute('data-rejection-note') || '').trim();
            if (!rejectionNote) {
                const marker = '[Rejection Note]';
                const idx = descriptionText.indexOf(marker);
                if (idx >= 0) {
                    rejectionNote = descriptionText.slice(idx + marker.length).trim();
                }
            }
            const statusText = (row.getAttribute('data-status') || '').toLowerCase();
            if (fdRejectionNoteRow && fdRejectionNote) {
                if (statusText === 'rejected' && rejectionNote) {
                    fdRejectionNote.textContent = rejectionNote;
                    fdRejectionNoteRow.style.display = 'block';
                } else {
                    fdRejectionNote.textContent = '-';
                    fdRejectionNoteRow.style.display = 'none';
                }
            }
            currentDetailsPhotoUrl = row.getAttribute('data-photo-url') || '';
            if (fdPhotoRow) fdPhotoRow.style.display = currentDetailsPhotoUrl ? 'block' : 'none';
            detailsModal.hidden = false;
            document.body.style.overflow = 'hidden';
        });
        row.addEventListener('keydown', function (event) {
            if (event.key === 'Enter' || event.key === ' ') {
                event.preventDefault();
                row.click();
            }
        });
    });

    function closeDetailsModal() {
        if (!detailsModal) return;
        detailsModal.hidden = true;
        document.body.style.overflow = '';
    }

    if (detailsClose) {
        detailsClose.addEventListener('click', closeDetailsModal);
    }
    if (detailsModal) {
        detailsModal.addEventListener('click', function (event) {
            if (event.target === detailsModal) {
                closeDetailsModal();
            }
        });
    }
    if (fdViewPhotoBtn) {
        fdViewPhotoBtn.addEventListener('click', function () {
            if (!currentDetailsPhotoUrl || !photoModal || !photoPreview) return;
            if (detailsModal) detailsModal.style.zIndex = '1300';
            photoModal.style.zIndex = '1400';
            photoPreview.src = currentDetailsPhotoUrl;
            photoModal.hidden = false;
            document.body.style.overflow = 'hidden';
        });
    }

    const inboxSearch = document.getElementById('feedbackInboxSearch');
    const inboxStatus = document.getElementById('feedbackInboxStatus');
    const inboxCount = document.getElementById('feedbackInboxCount');
    const inboxRows = Array.prototype.slice.call(document.querySelectorAll('[data-feedback-open="1"]'));
    function filterInboxRows() {
        if (!inboxRows.length) return;
        const q = ((inboxSearch && inboxSearch.value) || '').trim().toLowerCase();
        const status = ((inboxStatus && inboxStatus.value) || '').trim().toLowerCase();
        let shown = 0;
        inboxRows.forEach(function (row) {
            const hay = (row.getAttribute('data-search') || '').toLowerCase();
            const rowStatus = (row.getAttribute('data-status-filter') || '').toLowerCase();
            const okQ = q === '' || hay.indexOf(q) >= 0;
            const okStatus = status === '' || rowStatus === status;
            const visible = okQ && okStatus;
            row.style.display = visible ? '' : 'none';
            if (visible) shown += 1;
        });
        if (inboxCount) inboxCount.textContent = 'Showing ' + shown;
    }
    if (inboxSearch) inboxSearch.addEventListener('input', filterInboxRows);
    if (inboxStatus) inboxStatus.addEventListener('change', filterInboxRows);
    filterInboxRows();

    form.addEventListener('submit', async function (event) {
        event.preventDefault();

        if (district && barangay && altName && (!district.value || !barangay.value || !altName.value)) {
            showMessage('Please select District, Barangay, and Alternative Name.', false);
            return;
        }

        buildLocationValue();

        const data = new FormData(form);

        try {
            const response = await fetch('user-feedback.php', {
                method: 'POST',
                body: data
            });
            const raw = await response.text();
            let payload = null;
            try {
                payload = JSON.parse(raw);
            } catch (_jsonError) {
                const cleaned = raw.replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim();
                showMessage(cleaned || 'Server returned an invalid response. Please try again.', false);
                return;
            }
            if (payload.success) {
                showMessage(payload.message || 'Feedback submitted successfully.', true);
                form.reset();
                if (barangay) {
                    resetSelect(barangay, 'Select Barangay');
                    barangay.disabled = true;
                }
                if (altName) {
                    resetSelect(altName, 'Select Alternative Name');
                    altName.disabled = true;
                }
                if (locationInput) {
                    locationInput.value = '';
                }
                if (gpsLat) gpsLat.value = '';
                if (gpsLng) gpsLng.value = '';
                if (gpsAccuracy) gpsAccuracy.value = '';
                if (gpsMapUrl) gpsMapUrl.value = '';
                if (gpsAddress) gpsAddress.value = '';
                if (pinnedAddress) pinnedAddress.textContent = 'No pinned address yet.';
                if (photoStatus) photoStatus.textContent = 'No photo selected.';
                if (marker && map) {
                    map.removeLayer(marker);
                    marker = null;
                }
                try {
                    window.localStorage.removeItem(draftKey);
                } catch (_error) {
                    // ignore storage errors
                }
                window.setTimeout(function () {
                    window.location.reload();
                }, 700);
            } else {
                showMessage(payload.message || 'Unable to submit feedback.', false);
            }
        } catch (error) {
            console.error(error);
            showMessage('Unable to submit feedback right now. Please try again in a moment.', false);
        }
    });
});
























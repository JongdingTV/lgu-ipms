document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('userFeedbackForm');
    const message = document.getElementById('message');
    const category = document.getElementById('category');
    const district = document.getElementById('district');
    const barangay = document.getElementById('barangay');
    const altName = document.getElementById('alt_name');
    const locationInput = document.getElementById('location');
    const gpsPinBtn = document.getElementById('gpsPinBtn');
    const gpsLat = document.getElementById('gps_lat');
    const gpsLng = document.getElementById('gps_lng');
    const gpsAccuracy = document.getElementById('gps_accuracy');
    const gpsMapUrl = document.getElementById('gps_map_url');
    const photoInput = document.getElementById('photo');
    const removePhotoBtn = document.getElementById('removePhotoBtn');
    const photoStatus = document.getElementById('photoStatus');
    const mapContainer = document.getElementById('concernMap');

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

    function resetSelect(select, placeholder) {
        if (!select) return;
        select.innerHTML = '';
        const defaultOption = document.createElement('option');
        defaultOption.value = '';
        defaultOption.textContent = placeholder;
        select.appendChild(defaultOption);
        select.value = '';
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
        });

        altName.addEventListener('change', buildLocationValue);
    }


    if (gpsPinBtn) {
        gpsPinBtn.addEventListener('click', function () {
            if (!navigator.geolocation) {
                showMessage('Geolocation is not supported by your browser.', false);
                return;
            }

            navigator.geolocation.getCurrentPosition(function (position) {
                const lat = position.coords.latitude;
                const lng = position.coords.longitude;
                const acc = position.coords.accuracy || null;
                updateMapFields(lat, lng, acc);

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

                buildLocationValue();
                showMessage('Location pinned on map. You can drag the marker for exact spot.', true);
            }, function (error) {
                const msg = error && error.message ? error.message : 'Unable to get your current location.';
                showMessage(msg, false);
            }, {
                enableHighAccuracy: true,
                timeout: 12000,
                maximumAge: 0
            });
        });
    }


    function updateMapFields(lat, lng, accuracyMeters) {
        const latText = Number(lat).toFixed(6);
        const lngText = Number(lng).toFixed(6);
        if (gpsLat) gpsLat.value = latText;
        if (gpsLng) gpsLng.value = lngText;
        if (gpsAccuracy) gpsAccuracy.value = accuracyMeters ? String(Math.round(accuracyMeters)) : '';
        if (gpsMapUrl) gpsMapUrl.value = 'https://maps.google.com/?q=' + latText + ',' + lngText;
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
        window.L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '&copy; OpenStreetMap contributors'
        }).addTo(map);

        map.on('click', function (event) {
            const lat = event.latlng.lat;
            const lng = event.latlng.lng;
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
            updateMapFields(lat, lng, null);
            buildLocationValue();
        });
    }

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

            const payload = await response.json();
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
                if (photoStatus) photoStatus.textContent = 'No photo selected.';
                if (marker && map) {
                    map.removeLayer(marker);
                    marker = null;
                }
                window.setTimeout(function () {
                    window.location.reload();
                }, 700);
            } else {
                showMessage(payload.message || 'Unable to submit feedback.', false);
            }
        } catch (error) {
            console.error(error);
            showMessage('An unexpected error occurred while submitting feedback.', false);
        }
    });
});















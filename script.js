jQuery(document).ready(function ($) {


    function getCookie(name) {
        const value = `; ${document.cookie}`;
        const parts = value.split(`; ${name}=`);
        if (parts.length === 2) return parts.pop().split(';').shift();
        return null;
    }

    // Update button text with the selected location
    function updateLocationButton() {
        const selectedLocation = getCookie('selected_location');
        if (selectedLocation) {
            const selectedOptionText = $('#location-selector-dropdown option[value="' + selectedLocation + '"]').text();
            $('#change-location-btn').text(selectedOptionText || 'Set Location');
        } else {
            $('#change-location-btn').text('Set Location');
        }
    }

    // Initial button text update
    updateLocationButton();


    if (document.cookie.includes('selected_location')) {
        $('#location-selector-popup').fadeOut(function () {
            $(this).css('display', 'none');  
        });
    } else {
        $('#location-selector-popup').fadeIn();
    }

    if(document.cookie.includes('selected_location')){
    $('#change-location-btn').click(function () {
        $('#location-selector-popup').fadeIn();
    });
    }

    // Ensure Google Maps API is loaded before initializing autocomplete
    function initializeAutocomplete() {
        var input = document.getElementById('current-address');
        if (input) {
            var autocomplete = new google.maps.places.Autocomplete(input, {
                types: ['geocode'],
            });
            input.focus();
            autocomplete.addListener('place_changed', function () {
                var place = autocomplete.getPlace();
                if (place.geometry) {

                    $('#current-address').val(place.formatted_address);

                    const cityData = place.address_components.find(component => component.types.includes('locality'))?.long_name ||
                                     place.address_components.find(component => component.types.includes('administrative_area_level_2'))?.long_name ||
                                     'Unknown location';

                    let matchFound = false;
                    $('#location-selector-dropdown option').each(function () {
                        if ($(this).text().toLowerCase() === cityData.toLowerCase()) {
                            $(this).prop('selected', true);
                            matchFound = true;
                        }
                    });

                    if (!matchFound) {
                        alert(`The selected location (${cityData}) is not available. Please select manually.`);
                    }
                }
            });
        } else {
            console.error("Input element 'current-address' not found.");
        }
    }

    function loadGoogleMapsAPI() {
        if (typeof google !== 'undefined' && google.maps && google.maps.places) {
            initializeAutocomplete();
        } else {
            console.log('Google Maps API or Places library not loaded. Retrying...');
            setTimeout(loadGoogleMapsAPI, 1000); 
        }
    }

    loadGoogleMapsAPI();

    // Fetching location data using geolocation API
    $('#fetch-location').click(function () {
        if (navigator.geolocation) {
            navigator.geolocation.getCurrentPosition(
                function (position) {
                    const latitude = position.coords.latitude;
                    const longitude = position.coords.longitude;

                    const apiUrl = `https://nominatim.openstreetmap.org/reverse?lat=${latitude}&lon=${longitude}&format=json`;

                    fetch(apiUrl)
                        .then((response) => response.json())
                        .then((data) => {
                            if (data && data.address) {
                         
                                const fullAddress = data.display_name;

                                const cityData = data.address.city || data.address.town || data.address.village || 'Unknown location';

                                let matchFound = false;
                                $('#location-selector-dropdown option').each(function () {
                                    if ($(this).text().toLowerCase() === cityData.toLowerCase()) {
                                        $(this).prop('selected', true);
                                        matchFound = true;
                                    }
                                });

                                if (!matchFound) {
                                    alert(`The detected location (${cityData}) is not available in the dropdown. Please select manually.`);
                                }

                                $('#current-address').val(fullAddress);
                            } else {
                                alert('Unable to determine the city from the location.');
                            }
                        })
                        .catch((error) => {
                            console.error('Error fetching address:', error);
                            alert('Failed to fetch address. Please try again.');
                        });
                },
                function (error) {
                    let errorMsg;
                    switch (error.code) {
                        case error.PERMISSION_DENIED:
                            errorMsg = 'Location access denied.';
                            break;
                        case error.POSITION_UNAVAILABLE:
                            errorMsg = 'Location unavailable.';
                            break;
                        case error.TIMEOUT:
                            errorMsg = 'Location request timed out.';
                            break;
                        default:
                            errorMsg = 'An unknown error occurred.';
                            break;
                    }
                    alert(errorMsg);
                }
            );
        } else {
            alert('Geolocation is not supported by your browser.');
        }
    });

    // Submit location selection
    $('#location-selector-submit').click(function () {
        const locationId = $('#location-selector-dropdown').val();

        if (locationId) {
            $.post(
                location_selector_vars.ajaxUrl,
                {
                    action: 'location_selector_save',
                    location_id: locationId,
                    nonce: location_selector_vars.locationSelectorNonce,
                },
                function (response) {
                    if (response.success) {
                        const expireTime = new Date();
                        expireTime.setTime(expireTime.getTime() + 60 * 60 * 1000);
                        document.cookie = `selected_location=${locationId}; expires=${expireTime.toUTCString()}; path=/`;
                        $('#location-selector-popup').fadeOut();
                        location.reload();
                    } else {
                        console.error('Failed to save location:', response);
                    }
                }
            );
        } else {
            alert('Please select a location!');
        }
    });
});

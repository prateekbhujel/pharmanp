const placesByCountry = {
    NP: [
        'Kathmandu',
        'Pokhara',
        'Patan',
        'Bhaktapur',
        'Baneshwor, Kathmandu',
        'Maharajgunj, Kathmandu',
        'New Road, Kathmandu',
        'Thamel, Kathmandu',
        'Kalanki, Kathmandu',
        'Koteshwor, Kathmandu',
        'Chabahil, Kathmandu',
        'Boudha, Kathmandu',
        'Pulchowk, Lalitpur',
        'Lakeside, Pokhara',
        'Prithvi Chowk, Pokhara',
        'Srijana Chowk, Pokhara',
        'Biratnagar',
        'Birgunj',
        'Butwal',
        'Dharan',
        'Nepalgunj',
        'Hetauda',
    ],
    IN: [
        'Delhi',
        'Mumbai',
        'Kolkata',
        'Bengaluru',
        'Hyderabad',
        'Chennai',
        'Lucknow',
        'Patna',
        'Gorakhpur',
        'Siliguri',
    ],
    US: [
        'New York',
        'Los Angeles',
        'Chicago',
        'Houston',
        'Phoenix',
        'Philadelphia',
        'San Antonio',
        'San Diego',
        'Dallas',
        'San Jose',
    ],
    GB: [
        'London',
        'Manchester',
        'Birmingham',
        'Leeds',
        'Glasgow',
        'Liverpool',
        'Bristol',
        'Sheffield',
    ],
};

export function placeOptionsForCountry(countryCode = 'NP') {
    const places = placesByCountry[countryCode] || placesByCountry.NP;

    return places.map((place) => ({
        value: place,
        label: place,
    }));
}

# SmartBin

## Description

SmartBin is a project aimed at creating a smart waste management system. The objective is to design an electronic component capable of measuring the trash levels of multiple bins across a city. The collected data is then centralized and displayed on a website, providing real-time insights into the waste levels of each bin.

## Objectives

- Develop an electronic component to measure the trash level in bins.
- Deploy the component across multiple bins in a city.
- Gather and store the data in a centralized database.
- Create a web application to visualize the data, including:
  - A map showing the location of each bin.
  - Real-time trash levels for each bin.
  - Historical data for trash levels over time.

## Features

- **Real-Time Monitoring:** View the current trash levels of all bins on an interactive map.
- **Historical Data Visualization:** Analyze trends in trash levels over time using charts.
- **Geolocation Integration:** Automatically fetch latitude and longitude for bins based on their address.

## Technologies Used

- **Frontend:**
  - HTML, CSS, JavaScript
  - [Leaflet.js](https://leafletjs.com/) for interactive maps
  - [Chart.js](https://www.chartjs.org/) for data visualization
- **Backend:**
  - Python with Flask for the API
  - PostgreSQL for data storage

## How It Works

1. **Electronic Component:**

   - Measures the trash level in bins using sensors.
   - Sends the data to the backend server.

2. **Backend Server:**

   - Stores bin data (location, trash level, history) in a PostgreSQL database.
   - Provides RESTful APIs to fetch and update bin data.

3. **Frontend Website:**
   - Displays bin locations and trash levels on an interactive map.
   - Allows users to add new bins and view detailed information about each bin.


## Usage

- View Bins:
    - Open the map to see the location and trash levels of all bins.
- View Bin Details:
    - Click on a bin marker to view its details and historical data
  
## Future Enhancements

- Add notifications for bins that are nearly full.
- Integrate machine learning to predict trash levels based on historical data.
- Expand the system to support multiple cities.
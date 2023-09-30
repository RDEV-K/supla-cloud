const ChannelFunction = Object.freeze({
    UNSUPPORTED: -1,
    NONE: 0,
    SCENE: 2000,
    SCHEDULE: 2010,
    NOTIFICATION: 2020,
    CONTROLLINGTHEGATEWAYLOCK: 10,
    CONTROLLINGTHEGATE: 20,
    CONTROLLINGTHEGARAGEDOOR: 30,
    THERMOMETER: 40,
    HUMIDITY: 42,
    HUMIDITYANDTEMPERATURE: 45,
    OPENINGSENSOR_GATEWAY: 50,
    OPENINGSENSOR_GATE: 60,
    OPENINGSENSOR_GARAGEDOOR: 70,
    NOLIQUIDSENSOR: 80,
    CONTROLLINGTHEDOORLOCK: 90,
    OPENINGSENSOR_DOOR: 100,
    CONTROLLINGTHEROLLERSHUTTER: 110,
    CONTROLLINGTHEROOFWINDOW: 115,
    OPENINGSENSOR_ROLLERSHUTTER: 120,
    OPENINGSENSOR_ROOFWINDOW: 125,
    POWERSWITCH: 130,
    LIGHTSWITCH: 140,
    DIMMER: 180,
    RGBLIGHTING: 190,
    DIMMERANDRGBLIGHTING: 200,
    DEPTHSENSOR: 210,
    DISTANCESENSOR: 220,
    OPENINGSENSOR_WINDOW: 230,
    MAILSENSOR: 240,
    WINDSENSOR: 250,
    PRESSURESENSOR: 260,
    RAINSENSOR: 270,
    WEIGHTSENSOR: 280,
    WEATHER_STATION: 290,
    STAIRCASETIMER: 300,
    ELECTRICITYMETER: 310,
    IC_ELECTRICITYMETER: 315,
    IC_GASMETER: 320,
    IC_WATERMETER: 330,
    IC_HEATMETER: 340,
    THERMOSTAT: 400,
    THERMOSTATHEATPOLHOMEPLUS: 410,
    HVAC_THERMOSTAT: 420,
    HVAC_THERMOSTAT_AUTO: 422,
    VALVEOPENCLOSE: 500,
    VALVEPERCENTAGE: 510,
    GENERAL_PURPOSE_MEASUREMENT: 520,
    ACTION_TRIGGER: 700,
    DIGIGLASS_HORIZONTAL: 800,
    DIGIGLASS_VERTICAL: 810,
});

export default ChannelFunction;

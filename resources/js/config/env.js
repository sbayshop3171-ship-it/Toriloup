const ENV = {
    API_URL: import.meta.env.VITE_HOST,
    DEMO: import.meta.env.VITE_DEMO,
    API_KEY: import.meta.env.VITE_API_KEY,
    MAPBOX_ACCESS_TOKEN: import.meta.env.VITE_MAPBOX_ACCESS_TOKEN || ""
};
export default ENV;

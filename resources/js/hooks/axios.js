import { useAxios as vueuseAxios } from "@vueuse/integrations/useAxios";
import request from "../api/request";

export const useAxios = (url, config, options) => {
    return vueuseAxios(url, config, request, options);
}

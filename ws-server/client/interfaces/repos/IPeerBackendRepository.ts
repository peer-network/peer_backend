import { ClientTypes } from "../../domain/GemsResultsData";

export interface IBackendRepository {
    getDailyGemsResultData(day: ClientTypes.DayFilterType): Promise<ClientTypes.GemsResultsData>;
    getHelloData(): Promise<ClientTypes.HelloData>;
}
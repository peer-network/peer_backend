import { ClientTypes } from "../../domain/GemsResultsData";

export interface IBackendRepository {
    getHelloData(): Promise<ClientTypes.HelloData>;
}
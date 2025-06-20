import { ClientTypes } from "../../../domain/GemsResultsData"

export interface IMintUseCaseValidator {
    validateGemsResults(src : ClientTypes.GemsResultsData): void
}
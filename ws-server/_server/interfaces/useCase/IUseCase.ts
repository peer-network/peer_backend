import { IClientErrorCases } from "../../utils/errors/IClientErrorCases";
import CoreClientResponse from "../../domain/CoreClientResponse";

export interface IUseCase {
    readonly errors : IClientErrorCases
    
    execute(data: any): Promise<CoreClientResponse<any>>
}
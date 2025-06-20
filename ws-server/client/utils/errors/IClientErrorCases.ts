import { CodeDescription } from "./types";

export interface IClientErrorCases {}

export class ClientErrorCases implements IClientErrorCases  {
    public static generic : CodeDescription = {
        code: "40000",
        message: 'Generic Error'
    };
    public static ObjectContentsIsInvalid : CodeDescription = {
        code: "40000",
        message: 'Object contents is invalid'
    };    
}
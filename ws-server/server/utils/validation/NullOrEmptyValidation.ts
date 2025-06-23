import { CodeDescription } from "../errors/types";
import { ClientErrorCases, IClientErrorCases } from "../errors/IClientErrorCases";
import { ClientException } from "../errors/IClientException";

class EmptyOrNullValidatorErrors extends ClientErrorCases {
    public static nullOrUndefined : CodeDescription = {
        status: "error",
        code: "40000",
        message: 'Object is NULL or Underfined'
    };
    public static empty : CodeDescription = {
        status: "error",
        code: "40000",
        message: 'Object is Empty'
    };
    public static invalidType : CodeDescription = {
        status: "error",
        code: "40000",
        message: 'Object has wrong type'
    };
}

export class EmptyOrNullValidator {
    static validateAndGet<T>(src : any) : T {
        EmptyOrNullValidator.validate(src)
        const typedSrc = src as T
        if (!typedSrc) {
            throw new ClientException(EmptyOrNullValidatorErrors.invalidType)
        }
        return typedSrc
    }
    static validate(src : any) {
        if (src == null || src == undefined){
            throw new ClientException(EmptyOrNullValidatorErrors.nullOrUndefined)
        }
    }
}
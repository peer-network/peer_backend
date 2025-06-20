import { CodeDescription } from "../../utils/errors/types";
import { ClientErrorCases, IClientErrorCases } from "../../utils/errors/IClientErrorCases";
import { ClientException } from "../../utils/errors/IClientException";

class EmptyOrNullValidatorErrors extends ClientErrorCases {
    public static nullOrUndefined : CodeDescription = {
        code: "40000",
        message: 'Object is NULL or Underfined'
    };
    public static empty : CodeDescription = {
        code: "40000",
        message: 'Object is Empty'
    };
    public static invalidType : CodeDescription = {
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
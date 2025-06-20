import { ClientErrorCases, IClientErrorCases } from "../../../utils/errors/IClientErrorCases";
import { ClientException } from "../../../utils/errors/IClientException";
import { CodeDescription } from "../../../utils/errors/types";
import { logger } from "../../../utils/logger";
import { IAuthServiceValidator } from "./IAuthServiceValidator";
import { z } from "zod";
import { EmptyOrNullValidator } from "../../../utils/validation/NullOrEmptyValidation";

class AuthServiceValidatorImplZodErrors extends ClientErrorCases {
    
}

const LoginDataValidationSchema = z.object({
    status : z.string().nonempty(),
    ResponseCode : z.string().nonempty(),
    accessToken : z.string().nonempty(),
    refreshToken : z.string().nonempty()
});



export default class AuthServiceValidatorImplZod implements IAuthServiceValidator  {
    private errors = AuthServiceValidatorImplZodErrors

    validateLoginResponse(src: unknown): void {
        try {
            logger.info("AuthServiceValidatorImplZod")
            EmptyOrNullValidator.validate(src)
            LoginDataValidationSchema.parse(src);
        } catch(e) {
            const err = e as Error
            throw new ClientException(this.errors.ObjectContentsIsInvalid,"validateLoginResponse: " + err.name)
        }
    }
}
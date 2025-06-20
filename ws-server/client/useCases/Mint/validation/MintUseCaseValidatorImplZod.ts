import { ClientErrorCases, IClientErrorCases } from "../../../utils/errors/IClientErrorCases";
import { ClientException } from "../../../utils/errors/IClientException";
import { CodeDescription } from "../../../utils/errors/types";
import { logger } from "../../../utils/logger";
import { IMintUseCaseValidator } from "./IMintUseCaseValidator";
import { z } from "zod";
import { EmptyOrNullValidator } from "../../../utils/validation/NullOrEmptyValidation";
import {ClientTypes} from "../../../domain/GemsResultsData"
const DailyGemsUserValidationSchema = z.object({
    userid : z.string(),
    gems : z.number()
});

const DailyGemsValidationSchema = z.object({
    totalGems : z.number(),
    data : z.array(DailyGemsUserValidationSchema).nonempty()
});
  


class MintUseCaseValidatorErrors extends ClientErrorCases {
    public static GemsSumError : CodeDescription = {
        code: "40000",
        message: 'validateGemsResults: TotalGems and sum of the all UserGems are not equal'
    };
}



export default class MintUseCaseValidatorZod implements IMintUseCaseValidator {
    private errors = MintUseCaseValidatorErrors;

    validateGemsResults(src: unknown) {
        try {
            logger.info("MintUseCaseValidatorZod")
            EmptyOrNullValidator.validate(src)
            // console.log(src)
            DailyGemsValidationSchema.parse(src)
        } catch(e) {
            const err = e as ClientException
            if (!err) {
                throw new ClientException(this.errors.ObjectContentsIsInvalid, ": GemsResultsData ")
            }
            throw err
        }
        this.checkSumOfGems(src as ClientTypes.GemsResultsData)
    }

    checkSumOfGems(src : ClientTypes.GemsResultsData) {
        const initialValue = 0;
        const sumWithInitial = src.data.reduce(
            (accumulator, currentValue) => accumulator + currentValue.gems!,
            initialValue
        );
        if (sumWithInitial != src.totalGems) {
            throw new ClientException(this.errors.GemsSumError)
        }
    }
}
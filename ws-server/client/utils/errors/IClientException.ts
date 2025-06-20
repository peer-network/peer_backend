import { logger } from "../../utils/logger";
import { CodeDescription } from "./types";

export interface IClientException extends Error {
    code : string;
    message: string;
}

export class ClientException extends Error {
    name: string;
    code : string;
    message: string;
    stack?: string | undefined;

    constructor(
        error : CodeDescription, 
        detailsMessage : string | null = null
    ) {
        logger.error(error.message, detailsMessage)
        super()
        this.name = error.message
        this.code = error.code
        this.message = error.message + ", " + detailsMessage
        this.stack = error.message + ", " + detailsMessage
    }
}
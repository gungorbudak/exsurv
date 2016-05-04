# surv.R is a small R script for doing survival analysis
# requires survival, rjson and base64 packages
# prints values for plotting in JSON format
# usage Rscript surv.R <dataset in JSON format> <format>
# e.g. Rscript surv.R dataset.json png
# how to install a new package: install.packages('package_name', lib=c('/var/www/exsurv/html/app/libs'))

# add local library directory to library paths
.libPaths("/var/www/exsurv/html/app/libs")
library(survival)
library(rjson)
# draw plot and return it's string
getPlot <- function(curves, title, fmt = 'png') {
    if (fmt == 'png') {
        library(base64)
        input_file <- tempfile()
        output_file <- tempfile()
        png(input_file, width=720, height=720)
        plot(curves, xlab="Time (days)", ylab="Survival probability",
             col=c("red", "blue"), main=title)
        legend("topright", legend=c('High', 'Low'),
               col=c("red", "blue"), lty=1,
               horiz=TRUE, bty='n')
        garbage <- dev.off()
        encode(input_file, output_file)
        return(paste('data:image/png;base64',
                  paste(readLines(output_file), collapse=''),
                  sep=','))
    }
    if (fmt == 'svg') {
        input_file <- tempfile()
        svg(input_file, width=10, height=10)
        plot(curves, xlab="Time", ylab="Survival probability",
             col=c("red", "blue"), main=title)
        legend("topright", legend=c('High', 'Low'),
               col=c("red", "blue"), lty=1,
               horiz=TRUE, bty='n')
        garbage <- dev.off()
        return(paste(readLines(input_file), collapse=''))
    }
}
args <- commandArgs(TRUE)
# initial dataset generated from JSON file
data <- fromJSON(file=args[1])
# format
fmt <- args[2]
# survival analysis for each coming exon
for (i in 1:length(data)) {
    # plot title
    title <- paste(data[[i]]$info$exon_id, data[[i]]$info$cancer, sep=' / ')
    # do the analysis and get survival curves
    curves <- survfit(Surv(time, event) ~ group, data=as.data.frame(data[[i]]$data));
    # get rid of data field
    data[[i]]$data <- NULL
    # get the plot
    data[[i]]['plot'] <- getPlot(curves, title, fmt)
}
cat(toJSON(data))


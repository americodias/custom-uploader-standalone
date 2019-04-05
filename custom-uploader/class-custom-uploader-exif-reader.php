<?php

require_once('class-custom-uploader-coordinates-converter.php');

class ExifReader{

    public function __construct() { }

    /**
    * Returns the title
    **/
    private function get_title($iptc)
    {
        if (!empty($iptc["2#005"]))
            return implode(', ',$iptc["2#005"]);

        return null;
    }

    /**
    * Returns the description
    **/
    private function get_description($exif)
    {
        if ($exif) {
            if (@array_key_exists('ImageDescription', $exif['IFD0']))
                return $exif['IFD0']['ImageDescription'];
        }
        return null;
    }

    /**
    * Returns the camera make
    **/
    private function get_make($exif)
    {
        if ($exif) {
            if (@array_key_exists('Make', $exif['IFD0']))
                return $exif['IFD0']['Make'];
        }
        return null;
    }

    /**
    * Returns the camera model
    **/
    private function get_model($exif)
    {
        if ($exif) {
            if (@array_key_exists('Model', $exif['IFD0']))
                return $exif['IFD0']['Model'];
        }
        return null;
    }

    /**
    * Returns the lens model
    **/
    private function get_lens($exif)
    {
        if ($exif) {
            if (@array_key_exists('UndefinedTag:0xA434', $exif['EXIF']))
                return $exif['EXIF']['UndefinedTag:0xA434'];
        }
        return null;
    }

    /**
    * Returns the exposure time
    **/
    private function get_exposure($exif)
    {
        if ($exif) {
            if (@array_key_exists('ExposureTime', $exif['EXIF'])) {
                $e = explode("/", $exif['EXIF']['ExposureTime']);
                if(intval($e[1])==1)
                    return $e[0] . 's';
                else
                    return $exif['EXIF']['ExposureTime'] . 's';
            }
        }
        return null;
    }

    /**
    * Returns the aperture value
    **/
    private function get_aperture($exif)
    {
        if ($exif) {
            if (@array_key_exists('ApertureFNumber', $exif['COMPUTED']))
                return $exif['COMPUTED']['ApertureFNumber'];
        }
        return null;
    }

    /**
    * Returns the focal length in mm
    **/
    private function get_focal_length($exif)
    {
        if ($exif) {
            if (@array_key_exists('FocalLength', $exif['EXIF'])) {
                $fl = explode("/", $exif['EXIF']['FocalLength']);
                $fl = $fl[0] / $fl[1];

                return number_format($fl,0,'.','') . 'mm';
            }
        }
        return null;
    }

    /**
    * Returns the ISO
    **/
    private function get_iso($exif)
    {
        if ($exif) {
            if (@array_key_exists('ISOSpeedRatings', $exif['EXIF']))
                return 'ISO'.$exif['EXIF']['ISOSpeedRatings'];
        }
        return null;
    }

    /**
    * Returns the date and time
    **/
    private function get_date_time($exif)
    {
        if ($exif) {
            if (@array_key_exists('DateTimeOriginal', $exif['EXIF']))
                return $exif['EXIF']['DateTimeOriginal'];
        }
        return null;
    }

    /**
    * Returns the keywords
    **/
    private function get_keywords($iptc)
    {
        if (!empty($iptc["2#025"]))
            return implode(', ',$iptc["2#025"]);

        return null;
    }

    /**
    * Returns the flash value
    **/
    private function get_flash($exif)
    {
        if ($exif) {
            if (@array_key_exists('Flash', $exif['EXIF']))
                return $exif['EXIF']['Flash'];
        }
        return null;
    }

    /**
    * Returns the exposure bias
    **/
    private function get_exposure_bias($exif)
    {
        if ($exif) {
            if (@array_key_exists('ExposureBiasValue', $exif['EXIF']))
                return $exif['EXIF']['ExposureBiasValue'];
        }
        return null;
    }

    /**
    * Returns GPS latitude & longitude as decimal values
    **/
    private function get_gps($exif)
    {
        if ($exif && isset($exif['GPS']['GPSLatitude']) &&  isset($exif['GPS']['GPSLongitude'])){
            $lat = $exif['GPS']['GPSLatitude'];
            $lon = $exif['GPS']['GPSLongitude'];
            array_push($lat, $exif['GPS']['GPSLatitudeRef']);
            array_push($lon, $exif['GPS']['GPSLongitudeRef']);

            if (!$lat || !$lon) return null;

            $lat_decimal = CoordinatesConverter::dms_to_decimal($lat);
            $lon_decimal = CoordinatesConverter::dms_to_decimal($lon);

            return "$lat_decimal, $lon_decimal";
        }
        else
            return null;
    }

    /**
    * Returns the EXIF data object of a single image
    **/
    public function extract_data($image)
    {
        $data = null;
        $exif = null;
        $iptc = null;

        if ( is_callable('exif_read_data') ) {
            $exif  = exif_read_data($image, 0, true);

            if ( is_callable( 'iptcparse' ) ) {
                getimagesize( $image, $info );
                if ( ! empty( $info['APP13'] ) ) {
                    $iptc = iptcparse( $info['APP13'] );
                }
            }
            if ($exif){
                $data = array();
                $data['name'] = $exif['FILE']['FileName'];
                $data['title'] = $this->get_title($iptc);
                $data['description'] = $this->get_description($exif);
                $data['make'] = $this->get_make($exif);
                $data['model'] = $this->get_model($exif);
                $data['lens'] = $this->get_lens($exif);
                $data['exposure'] = $this->get_exposure($exif);
                $data['aperture'] = $this->get_aperture($exif);
                $data['focallength'] = $this->get_focal_length($exif);
                $data['iso'] = $this->get_iso($exif);
                $data['gps'] = $this->get_gps($exif);
                $data['date'] = $this->get_date_time($exif);
                $data['size'] = floor(($exif['FILE']['FileSize'] / 1024))."KB";
                $data['keywords'] = $this->get_keywords($iptc);
                $data['exposure_bias'] = $this->get_exposure_bias($exif);
                $data['flash'] = $this->get_flash($exif);
            }
            array_walk($data, function(&$value, $index){
                if (is_string($value))
                    $value = sanitize_text_field($value);
            });
        }

        return $data;
    }
}

function ExifReaderTest($fileName) {
    $exifReader = new ExifReader();
    $data = $exifReader->readImage($fileName);

    echo "Name:         " . $data['name'] . '<br/>';
    echo "Title:        " . $data['title'] . '<br/>';
    echo "Description:  " . $data['description'] . '<br/>';
    echo "Make:         " . $data['make'] . '<br/>';
    echo "Model:        " . $data['model'] . '<br/>';
    echo "Lens:         " . $data['lens'] . '<br/>';
    echo "Exposure:     " . $data['exposure'] . '<br/>';
    echo "Aperture:     " . $data['aperture'] . '<br/>';
    echo "Focal Length: " . $data['focallength'] . '<br/>';
    echo "ISO:          " . $data['iso'] . '<br/>';
    echo "GPS:          " . $data['gps'][0] . ',' . $data['gps'][1] . '<br/>';
    echo "Date:         " . $data['date'] . '<br/>';
    echo "Size:         " . $data['size'] . '<br/>';
    echo "Keywords:     " . $data['keywords'] . '<br/>';

    return $data;
}
?>
